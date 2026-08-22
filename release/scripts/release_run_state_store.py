#!/usr/bin/env python3
"""Persist release-run state through retained descriptor-relative authority."""

from __future__ import annotations

import ctypes
import errno
import fcntl
import hashlib
import json
import os
import secrets
import stat
import sys
from typing import Any

from release_artifact_store import _chain_still_linked, _directory_flags, _open_parent


FAILURE = "failed"
INDETERMINATE = "indeterminate"
MAX_STATE_BYTES = 16 * 1024 * 1024
RENAME_NOREPLACE = 1

STOP_CONTRACTS: dict[str, tuple[str, str, str]] = {
    "failed": ("policy_blocked", "release.prepare.state_persistence_failed", "repair_release_run_storage"),
    "create_conflict": ("conflict", "release.prepare.run_identity_conflict", "retry_release_preparation_with_new_run"),
    "state_indeterminate": ("evidence_indeterminate", "release.prepare.state_persistence_indeterminate", "reconcile_named_release_run"),
    "artifact_indeterminate": ("evidence_indeterminate", "release.prepare.artifacts_indeterminate", "reconcile_named_release_run"),
    "baseline_refusal": ("authority_required", "release.prepare.baseline_resolution_refused", "obtain_current_baseline_authority"),
    "baseline_failure": ("policy_blocked", "release.prepare.baseline_resolution_failed", "repair_baseline_resolution_provider"),
    "baseline_uncertainty": ("evidence_indeterminate", "release.prepare.baseline_resolution_uncertain", "reconcile_baseline_resolution"),
    "baseline_drift": ("stale_plan", "release.prepare.baseline_resolution_drift", "create_current_release_plan"),
    "baseline_missing": ("policy_blocked", "release.prepare.baseline_tag_missing", "repair_baseline_authority"),
    "baseline_ambiguous": ("policy_blocked", "release.prepare.baseline_tag_ambiguous", "repair_baseline_authority"),
    "baseline_duplicate_normalized": ("policy_blocked", "release.prepare.baseline_tag_duplicate_normalized", "repair_baseline_authority"),
    "baseline_non_ancestor": ("policy_blocked", "release.prepare.baseline_tag_non_ancestor", "repair_baseline_authority"),
    "support_policy_drift": ("stale_plan", "release.prepare.support_policy_drift", "create_current_release_plan"),
    "approval_drift": ("authority_required", "release.prepare.approval_authority_drift", "obtain_current_release_approval"),
    "evidence_drift": ("stale_plan", "release.prepare.evidence_authority_drift", "create_current_release_plan"),
    "compatibility_drift": ("stale_plan", "release.prepare.compatibility_authority_drift", "create_current_release_plan"),
    "authority_refused": ("authority_required", "release.prepare.plan_authority_refused", "obtain_current_release_authority"),
    "authority_failed": ("policy_blocked", "release.prepare.plan_authority_failed", "repair_release_authority_provider"),
    "authority_uncertain": ("evidence_indeterminate", "release.prepare.plan_authority_uncertain", "reconcile_release_plan_authority"),
    "stale": ("stale_plan", "release.prepare.resume_plan_drift", "create_current_release_plan"),
    "indeterminate": ("evidence_indeterminate", "release.prepare.resume_state_indeterminate", "reconcile_named_release_run"),
}


class ArtifactProof:
    """Pins verified immutable artifact names and bytes through state publication."""

    def __init__(self, parent_fd: int, names: list[str]):
        self.parent_fd = parent_fd
        self.pins: list[tuple[str, int, bytes]] = []
        descriptor: int | None = None
        try:
            for name in names:
                descriptor = os.open(name, os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC, dir_fd=parent_fd)
                identity = os.fstat(descriptor)
                if not stat.S_ISREG(identity.st_mode) or identity.st_size > MAX_STATE_BYTES:
                    raise OSError("artifact is not a bounded regular file")
                contents = b""
                while len(contents) <= MAX_STATE_BYTES:
                    chunk = os.read(descriptor, min(65536, MAX_STATE_BYTES + 1 - len(contents)))
                    if not chunk:
                        break
                    contents += chunk
                if len(contents) > MAX_STATE_BYTES or os.read(descriptor, 1):
                    raise OSError("artifact exceeds protocol bound")
                self.pins.append((name, descriptor, contents))
                descriptor = None
        except OSError:
            if descriptor is not None:
                os.close(descriptor)
            self.close()
            raise

    def valid(self) -> bool:
        try:
            for name, descriptor, contents in self.pins:
                if not _name_matches_descriptor(self.parent_fd, name, descriptor):
                    return False
                os.lseek(descriptor, 0, os.SEEK_SET)
                reread = b""
                while len(reread) <= MAX_STATE_BYTES:
                    chunk = os.read(descriptor, min(65536, MAX_STATE_BYTES + 1 - len(reread)))
                    if not chunk:
                        break
                    reread += chunk
                if reread != contents or len(reread) > MAX_STATE_BYTES or os.read(descriptor, 1):
                    return False
            return True
        except OSError:
            return False

    def contents(self, name: str) -> bytes | None:
        """Returns the exact bytes retained for one pinned artifact name."""
        for pinned_name, _, contents in self.pins:
            if pinned_name == name:
                return contents
        return None

    def close(self) -> None:
        for _, descriptor, _ in self.pins:
            try:
                os.close(descriptor)
            except OSError:
                pass
        self.pins = []


def _pin_artifacts(parent_fd: int, names: list[str]) -> ArtifactProof | None:
    proof: ArtifactProof | None = None
    try:
        proof = ArtifactProof(parent_fd, names)
        if proof.valid():
            return proof
        proof.close()
        return None
    except OSError:
        if proof is not None:
            proof.close()
        return None


def _inject_artifact_publication_fault(parent_fd: int, fault: str, manifest_name: str, handoff_name: str, replacement: str) -> None:
    try:
        if fault in {"manifest_unlink_after_read", "manifest_unlink_before_state_publish"}:
            os.unlink(manifest_name, dir_fd=parent_fd)
        elif fault in {"handoff_substitute_after_read", "handoff_substitute_before_state_publish"}:
            _replace_opened_name(parent_fd, handoff_name, replacement)
    except OSError:
        pass


def _inject_artifact_postpublication_fault(
    parent_fd: int, fault: str, manifest_name: str, handoff_name: str, replacement: str
) -> None:
    try:
        if fault == "manifest_unlink_after_state_publish":
            os.unlink(manifest_name, dir_fd=parent_fd)
        elif fault == "handoff_substitute_after_state_publish":
            _replace_opened_name(parent_fd, handoff_name, replacement)
    except OSError:
        pass


def _digest_identity(value: str) -> bool:
    return len(value) == 64 and all(character in "0123456789abcdef" for character in value)


def _canonical(payload: dict[str, Any]) -> bytes:
    return (json.dumps(payload, ensure_ascii=False, separators=(",", ":"), sort_keys=True) + "\n").encode()


def _artifact(proof: ArtifactProof, name: str, id_field: str, identity: str) -> dict[str, Any] | None:
    contents = proof.contents(name)
    if contents is None or not contents.endswith(b"\n") or contents.endswith(b"\n\n"):
        return None
    try:
        payload = json.loads(contents[:-1])
    except (UnicodeDecodeError, json.JSONDecodeError):
        return None
    if not isinstance(payload, dict) or _canonical(payload) != contents or payload.get(id_field) != identity:
        return None
    unsigned = dict(payload)
    unsigned.pop(id_field)
    if hashlib.sha256(_canonical(unsigned)[:-1]).hexdigest() != identity:
        return None
    return unsigned


def _release_plan(proof: ArtifactProof, plan_id: str) -> dict[str, Any] | None:
    plan = _artifact(proof, plan_id + ".json", "plan_id", plan_id)
    expected_keys = {
        "schema_version", "approved_version", "minimum_release_class", "release_class",
        "source_commit_oid", "baseline", "support_policy_identity", "expected_effect_classes",
        "evidence_requirements", "evidence_manifest_digest", "compatibility_exceptions",
        "patch_exception_authorities", "required_approvals", "release_approval_authority",
    }
    if (plan is None or set(plan) != expected_keys
            or plan.get("schema_version") != "fight-common.release-plan/v1"
            or not isinstance(plan.get("baseline"), dict)):
        return None
    return plan


def _artifact_authority(plan: dict[str, Any]) -> tuple[dict[str, Any], dict[str, Any]]:
    return (
        {
            "release": plan.get("release_approval_authority"),
            "required": plan.get("required_approvals"),
        },
        {
            "approved_version": plan.get("approved_version"),
            "baseline": {
                "version": (plan.get("baseline") or {}).get("version"),
                "peeled_commit_oid": (plan.get("baseline") or {}).get("peeled_commit_oid"),
                "tag_name": (plan.get("baseline") or {}).get("tag_name"),
                "tag_object_oid": (plan.get("baseline") or {}).get("tag_object_oid"),
            },
            "compatibility_exceptions": plan.get("compatibility_exceptions"),
            "evidence_manifest_digest": plan.get("evidence_manifest_digest"),
            "evidence_requirements": plan.get("evidence_requirements"),
            "expected_effect_classes": plan.get("expected_effect_classes"),
            "minimum_release_class": plan.get("minimum_release_class"),
            "patch_exception_authorities": plan.get("patch_exception_authorities"),
            "release_class": plan.get("release_class"),
            "source_commit_oid": plan.get("source_commit_oid"),
            "support_policy_identity": plan.get("support_policy_identity"),
        },
    )


def _artifact_pair(
    proof: ArtifactProof,
    plan_id: str,
    run_id: str,
    manifest_id: str,
    handoff_id: str,
    status: str,
    stop_state: dict[str, str] | None,
    next_action: str,
    activation: dict[str, Any],
    verified_evidence: dict[str, Any],
) -> bool:
    plan = _release_plan(proof, plan_id)
    manifest = _artifact(proof, manifest_id + ".evidence-manifest.json", "manifest_id", manifest_id)
    handoff = _artifact(proof, handoff_id + ".phase-handoff.json", "handoff_id", handoff_id)
    artifact_keys = {
        "activation", "approvals", "bindings", "next_action", "phase", "plan_id", "run_id",
        "schema_version", "status", "stop_state", "verified_evidence",
    }
    common = {
        "plan_id": plan_id, "run_id": run_id, "phase": "preparation", "status": status,
        "stop_state": stop_state, "next_action": {"action": next_action}, "activation": activation,
        "verified_evidence": verified_evidence,
    }
    if (plan is None or manifest is None or handoff is None
            or set(manifest) != artifact_keys or set(handoff) != artifact_keys
            or any(manifest.get(key) != value or handoff.get(key) != value for key, value in common.items())):
        return False
    approvals, bindings = _artifact_authority(plan)
    if (manifest.get("schema_version") != "fight-common.release-evidence-manifest/v1"
            or handoff.get("schema_version") != "fight-common.release-phase-handoff/v1"
            or manifest.get("approvals") != approvals or manifest.get("bindings") != bindings):
        return False
    expected = dict(manifest)
    expected["schema_version"] = "fight-common.release-phase-handoff/v1"
    bindings = expected.get("bindings")
    if not isinstance(bindings, dict):
        return False
    expected["bindings"] = {**bindings, "evidence_manifest_id": manifest_id}
    return handoff == expected


def _finalization_artifacts(
    proof: ArtifactProof,
    plan_id: str,
    run_id: str,
    manifest_id: str,
    handoff_id: str,
    history: bytes,
    projection: bytes,
) -> bool:
    return _artifact_pair(
        proof, plan_id, run_id, manifest_id, handoff_id, "prepared", None,
        "package_release_run",
        {"mode": "projection_bound", "projection_must_bind_artifact_ids": True,
         "required_projection_state": "prepared"},
        {"history_sha256": hashlib.sha256(history).hexdigest(),
         "postconditions": ["immutable_plan_revalidated", "prepared_run_projection_published"],
         "projection_sha256": hashlib.sha256(projection).hexdigest()},
    )


def _pin_finalization_artifacts(
    authority: "Authority",
    plan_id: str,
    run_id: str,
    manifest_id: str,
    handoff_id: str,
    history: bytes,
    projection: bytes,
) -> ArtifactProof | None:
    proof = _pin_artifacts(authority.output_fd, [
        plan_id + ".json",
        manifest_id + ".evidence-manifest.json",
        handoff_id + ".phase-handoff.json",
    ])
    if proof is None or not _finalization_artifacts(
        proof, plan_id, run_id, manifest_id, handoff_id, history, projection
    ):
        if proof is not None:
            proof.close()
        return None
    return proof


def _finalization_inputs(
    history: bytes,
    events: list[dict[str, Any]],
    plan_id: str,
    run_id: str,
    manifest_id: str,
    handoff_id: str,
) -> tuple[bytes, bytes] | None:
    lines = history.rstrip(b"\n").split(b"\n")
    for index in range(len(events) - 1, -1, -1):
        event = events[index]
        if (
            event.get("operation") == "finalize_release_preparation_evidence"
            and event.get("prerequisite_evidence_manifest_id") == manifest_id
            and event.get("prerequisite_phase_handoff_id") == handoff_id
        ):
            if index == 0:
                return None
            prepared_history = b"\n".join(lines[:index]) + b"\n"
            validated = _validate_history(prepared_history, plan_id, run_id)
            if validated is None:
                return None
            return prepared_history, validated[1]
    return None


def _is_stop_truth(event: dict[str, Any]) -> bool:
    return event.get("operation") == "stop_release_preparation" or (
        event.get("operation") == "compensate_artifact_proof_loss"
        and event.get("restored_operation") == "stop_release_preparation"
    )


def _compensated(
    plan_id: str,
    run_id: str,
    failed: dict[str, Any],
    restored: dict[str, Any],
) -> tuple[bytes, bytes]:
    sequence = failed["sequence"] + 1
    event: dict[str, Any] = {
        "sequence": sequence,
        "from": failed["state"],
        "state": restored["state"],
        "operation": "compensate_artifact_proof_loss",
        "plan_id": plan_id,
        "run_id": run_id,
        "compensated_sequence": failed["sequence"],
        "restored_operation": restored["operation"],
        "next_action": restored["next_action"],
    }
    if _is_stop_truth(restored):
        for key in (
            "stop_code", "stop_state", "finding_id", "resume_state", "resume_sequence",
            "resume_next_action", "evidence_manifest_id", "phase_handoff_id",
        ):
            if key in restored:
                event[key] = restored[key]
        projection = json.loads(_stop_projection(event))
    else:
        for key in (
            "prerequisite_evidence_manifest_id", "prerequisite_phase_handoff_id",
        ):
            if key in restored:
                event[key] = restored[key]
        projection = {
            "schema_version": "fight-common.release-run-state/v1",
            "plan_id": plan_id,
            "run_id": run_id,
            "sequence": sequence,
            "state": restored["state"],
            "next_action": restored["next_action"],
        }
        for key in (
            "prerequisite_evidence_manifest_id", "prerequisite_phase_handoff_id",
        ):
            if key in restored:
                projection[key] = restored[key]
    projection["compensated_artifact_proof_loss_sequence"] = failed["sequence"]
    return _canonical(event), _canonical(projection)


def _compensate_publication(
    authority: "Authority",
    history: bytes,
    failed: dict[str, Any],
    restored: dict[str, Any],
    root: str,
    relative_output: str,
    run_id: str,
) -> bool:
    transition, projection = _compensated(
        str(failed["plan_id"]), str(failed["run_id"]), failed, restored
    )
    return (
        _publish(authority, "history.jsonl", history + transition, "", "history", root, relative_output, run_id, "")
        and _publish(authority, "projection.json", projection, "", "projection", root, relative_output, run_id, "")
    )


def _quarantine_new_run(authority: "Authority", visible_name: str) -> bool:
    if authority.run_fd is None or authority.runs_fd is None:
        return False
    try:
        rejected = ".rejected-" + visible_name + "-" + secrets.token_hex(8)
        os.rename(visible_name, rejected, src_dir_fd=authority.runs_fd, dst_dir_fd=authority.runs_fd)
        os.fsync(authority.runs_fd)
        authority.components[-1] = rejected
        return True
    except OSError:
        return False


def _publish_run_directory_noreplace(parent_fd: int, staged_name: str, visible_name: str) -> bool:
    """Atomically reveals one staged run without ever replacing an existing name."""
    libc = ctypes.CDLL(None, use_errno=True)

    try:
        renameat2 = libc.renameat2
    except AttributeError as error:
        raise OSError(errno.ENOTSUP, "atomic no-replace directory publication is unavailable") from error

    renameat2.argtypes = [
        ctypes.c_int,
        ctypes.c_char_p,
        ctypes.c_int,
        ctypes.c_char_p,
        ctypes.c_uint,
    ]
    renameat2.restype = ctypes.c_int
    result = renameat2(
        parent_fd,
        os.fsencode(staged_name),
        parent_fd,
        os.fsencode(visible_name),
        RENAME_NOREPLACE,
    )

    if result == 0:
        return True

    error = ctypes.get_errno()
    if error in {errno.EEXIST, errno.ENOTEMPTY}:
        return False

    raise OSError(error, os.strerror(error), visible_name)


def _stop_artifacts(
    proof: ArtifactProof,
    plan_id: str,
    run_id: str,
    stop_state: str,
    finding_id: str,
    next_action: str,
    manifest_id: str,
    handoff_id: str,
    activation_mode: str,
) -> bool:
    activation = {"mode": activation_mode, "projection_must_bind_artifact_ids": activation_mode == "projection_bound"}
    if activation_mode == "projection_bound":
        activation["required_projection_state"] = stop_state
    return _artifact_pair(
        proof, plan_id, run_id, manifest_id, handoff_id, stop_state,
        {"finding_id": finding_id, "status": stop_state}, next_action, activation,
        {"postconditions": []},
    )


def _artifactless_stop_allowed(stop_code: str, stop_state: str, finding_id: str, next_action: str) -> bool:
    return (stop_code == "artifact_indeterminate"
            and stop_state == "evidence_indeterminate"
            and finding_id == "release.prepare.evidence_persistence_failed"
            and next_action == "repair_release_evidence_storage")


def _stop_contract_allowed(stop_code: str, stop_state: str, finding_id: str, next_action: str) -> bool:
    return STOP_CONTRACTS.get(stop_code) == (stop_state, finding_id, next_action) or _artifactless_stop_allowed(
        stop_code, stop_state, finding_id, next_action
    )


def _stop_receipt(
    status: str,
    history_path: str,
    projection_path: str,
    stop_code: str,
    stop_state: str,
    finding_id: str,
    next_action: str,
    sequence: int,
    manifest_id: str,
    handoff_id: str,
) -> dict[str, Any]:
    receipt: dict[str, Any] = {
        "status": status,
        "history_path": history_path,
        "projection_path": projection_path,
        "stop_code": stop_code,
        "stop_state": stop_state,
        "finding_id": finding_id,
        "next_action": next_action,
        "sequence": sequence,
        "state": stop_state,
    }
    if manifest_id and handoff_id:
        receipt["prerequisite_evidence_manifest_id"] = manifest_id
        receipt["prerequisite_phase_handoff_id"] = handoff_id
    return receipt


def _planned(plan_id: str, run_id: str) -> tuple[bytes, bytes]:
    return (
        _canonical({
            "sequence": 1,
            "from": None,
            "state": "planned",
            "operation": "create_release_run",
            "plan_id": plan_id,
            "run_id": run_id,
            "next_action": {"action": "prepare_release_run"},
        }),
        _canonical({
            "schema_version": "fight-common.release-run-state/v1",
            "plan_id": plan_id,
            "run_id": run_id,
            "sequence": 1,
            "state": "planned",
            "next_action": {"action": "prepare_release_run"},
        }),
    )


def _prepared(plan_id: str, run_id: str) -> tuple[bytes, bytes, bytes]:
    planned_history, _ = _planned(plan_id, run_id)
    transition = _canonical({
        "sequence": 2,
        "from": "planned",
        "state": "prepared",
        "operation": "prepare_release_run",
        "plan_id": plan_id,
        "run_id": run_id,
        "next_action": {"action": "finalize_release_preparation_evidence"},
    })
    projection = _canonical({
        "schema_version": "fight-common.release-run-state/v1",
        "plan_id": plan_id,
        "run_id": run_id,
        "sequence": 2,
        "state": "prepared",
        "next_action": {"action": "finalize_release_preparation_evidence"},
    })
    return planned_history + transition, projection, transition


def _finalized(
    plan_id: str,
    run_id: str,
    manifest_id: str,
    handoff_id: str,
) -> tuple[bytes, bytes]:
    transition = _canonical({
        "sequence": 3,
        "from": "prepared",
        "state": "prepared",
        "operation": "finalize_release_preparation_evidence",
        "plan_id": plan_id,
        "run_id": run_id,
        "prerequisite_evidence_manifest_id": manifest_id,
        "prerequisite_phase_handoff_id": handoff_id,
        "next_action": {"action": "package_release_run"},
    })
    projection = _canonical({
        "schema_version": "fight-common.release-run-state/v1",
        "plan_id": plan_id,
        "run_id": run_id,
        "sequence": 3,
        "state": "prepared",
        "prerequisite_evidence_manifest_id": manifest_id,
        "prerequisite_phase_handoff_id": handoff_id,
        "next_action": {"action": "package_release_run"},
    })
    return transition, projection


def _prepared_after(plan_id: str, run_id: str, prior: dict[str, Any]) -> tuple[bytes, bytes]:
    sequence = prior["sequence"] + 1
    transition = _canonical({
        "sequence": sequence,
        "from": "planned",
        "state": "prepared",
        "operation": "prepare_release_run",
        "plan_id": plan_id,
        "run_id": run_id,
        "next_action": {"action": "finalize_release_preparation_evidence"},
    })
    projection = _canonical({
        "schema_version": "fight-common.release-run-state/v1",
        "plan_id": plan_id,
        "run_id": run_id,
        "sequence": sequence,
        "state": "prepared",
        "next_action": {"action": "finalize_release_preparation_evidence"},
    })
    return transition, projection


def _finalized_after(
    plan_id: str,
    run_id: str,
    prior: dict[str, Any],
    manifest_id: str,
    handoff_id: str,
) -> tuple[bytes, bytes]:
    sequence = prior["sequence"] + 1
    transition = _canonical({
        "sequence": sequence,
        "from": "prepared",
        "state": "prepared",
        "operation": "finalize_release_preparation_evidence",
        "plan_id": plan_id,
        "run_id": run_id,
        "prerequisite_evidence_manifest_id": manifest_id,
        "prerequisite_phase_handoff_id": handoff_id,
        "next_action": {"action": "package_release_run"},
    })
    projection = _canonical({
        "schema_version": "fight-common.release-run-state/v1",
        "plan_id": plan_id,
        "run_id": run_id,
        "sequence": sequence,
        "state": "prepared",
        "prerequisite_evidence_manifest_id": manifest_id,
        "prerequisite_phase_handoff_id": handoff_id,
        "next_action": {"action": "package_release_run"},
    })
    return transition, projection


def _recovered(plan_id: str, run_id: str, stopped: dict[str, Any]) -> tuple[bytes, bytes]:
    sequence = stopped["sequence"] + 1
    event = {
        "sequence": sequence,
        "from": stopped["state"],
        "state": stopped["resume_state"],
        "operation": "recover_release_preparation_stop",
        "plan_id": plan_id,
        "run_id": run_id,
        "recovered_stop_sequence": stopped["sequence"],
        "next_action": stopped["resume_next_action"],
    }
    projection = {
        "schema_version": "fight-common.release-run-state/v1",
        "plan_id": plan_id,
        "run_id": run_id,
        "sequence": sequence,
        "state": stopped["resume_state"],
        "recovered_stop_sequence": stopped["sequence"],
        "next_action": stopped["resume_next_action"],
    }
    return _canonical(event), _canonical(projection)


def _stop_projection(event: dict[str, Any]) -> bytes:
    projection = {
        "schema_version": "fight-common.release-run-state/v1",
        "plan_id": event["plan_id"],
        "run_id": event["run_id"],
        "sequence": event["sequence"],
        "state": event["stop_state"],
        "stop_code": event["stop_code"],
        "stop_state": event["stop_state"],
        "finding_id": event["finding_id"],
    }
    if event["sequence"] > 1:
        projection.update({
            "resume_state": event["resume_state"],
            "resume_sequence": event["resume_sequence"],
            "resume_next_action": event["resume_next_action"],
        })
    if "evidence_manifest_id" in event:
        projection.update({
            "evidence_manifest_id": event["evidence_manifest_id"],
            "phase_handoff_id": event["phase_handoff_id"],
        })
    projection["next_action"] = event["next_action"]
    return _canonical(projection)


def _validate_stop(
    event: dict[str, Any],
    prior: dict[str, Any] | None,
    plan_id: str,
    run_id: str,
) -> bool:
    action = event.get("next_action")
    manifest_id = event.get("evidence_manifest_id")
    handoff_id = event.get("phase_handoff_id")
    has_bindings = manifest_id is not None or handoff_id is not None
    stop_code = event.get("stop_code")
    stop_state = event.get("stop_state")
    finding_id = event.get("finding_id")
    next_action = action.get("action") if isinstance(action, dict) else None
    contract = STOP_CONTRACTS.get(stop_code) if isinstance(stop_code, str) else None
    exact_artifactless = (
        isinstance(stop_code, str)
        and isinstance(stop_state, str)
        and isinstance(finding_id, str)
        and isinstance(next_action, str)
        and _artifactless_stop_allowed(stop_code, stop_state, finding_id, next_action)
    )
    expected_keys = {
        "sequence", "from", "state", "stop_code", "stop_state", "finding_id",
        "operation", "plan_id", "run_id", "next_action",
    }
    if prior is not None:
        expected_keys.update({"resume_state", "resume_sequence", "resume_next_action"})
    if has_bindings:
        expected_keys.update({"evidence_manifest_id", "phase_handoff_id"})
    if (
        set(event) != expected_keys
        or event.get("operation") != "stop_release_preparation"
        or event.get("plan_id") != plan_id
        or event.get("run_id") != run_id
        or not isinstance(event.get("stop_code"), str)
        or event["stop_code"] == ""
        or not isinstance(event.get("stop_state"), str)
        or event["stop_state"] == ""
        or event.get("state") != event["stop_state"]
        or not isinstance(event.get("finding_id"), str)
        or event["finding_id"] == ""
        or not isinstance(action, dict)
        or list(action) != ["action"]
        or not isinstance(action.get("action"), str)
        or action["action"] == ""
        or has_bindings and (
            not isinstance(manifest_id, str)
            or not isinstance(handoff_id, str)
            or not _digest_identity(manifest_id)
            or not _digest_identity(handoff_id)
        )
        or contract != (stop_state, finding_id, next_action) and not exact_artifactless
        or not has_bindings and not exact_artifactless
    ):
        return False
    if prior is None:
        return (
            event.get("sequence") == 1
            and event.get("from") is None
            and not any(key in event for key in ("resume_state", "resume_sequence", "resume_next_action"))
        )
    return (
        event.get("sequence") == prior["sequence"] + 1
        and event.get("from") == prior["state"]
        and event.get("resume_state") == prior["state"]
        and event.get("resume_sequence") == prior["sequence"]
        and event.get("resume_next_action") == prior["next_action"]
    )


def _validate_history(
    history: bytes,
    plan_id: str,
    run_id: str,
) -> tuple[list[dict[str, Any]], bytes, bytes | None] | None:
    if not history or not history.endswith(b"\n"):
        return None
    raw_lines = history[:-1].split(b"\n")
    events: list[dict[str, Any]] = []
    expected_projection: bytes | None = None
    prior_projection: bytes | None = None
    for index, raw in enumerate(raw_lines):
        try:
            event = json.loads(raw)
        except (ValueError, json.JSONDecodeError):
            return None
        if (
            not isinstance(event, dict)
            or raw + b"\n" != _canonical(event)
            or event.get("plan_id") != plan_id
            or event.get("run_id") != run_id
            or event.get("sequence") != index + 1
        ):
            return None
        prior = events[-1] if events else None
        prior_projection = expected_projection
        if prior is None:
            planned_history, planned_projection = _planned(plan_id, run_id)
            if raw + b"\n" == planned_history:
                expected_projection = planned_projection
            elif _validate_stop(event, None, plan_id, run_id):
                expected_projection = _stop_projection(event)
            else:
                return None
        elif event.get("operation") == "compensate_artifact_proof_loss":
            if len(events) < 2 or prior.get("operation") not in {
                "finalize_release_preparation_evidence",
                "recover_release_preparation_stop",
                "stop_release_preparation",
            }:
                return None
            restored = events[-2]
            transition, projection = _compensated(plan_id, run_id, prior, restored)
            if raw + b"\n" != transition:
                return None
            expected_projection = projection
        elif _is_stop_truth(prior):
            if not all(key in prior for key in ("resume_state", "resume_sequence", "resume_next_action")):
                return None
            recovery, recovery_projection = _recovered(plan_id, run_id, prior)
            if raw + b"\n" != recovery:
                return None
            expected_projection = recovery_projection
        elif event.get("operation") == "stop_release_preparation":
            if not _validate_stop(event, prior, plan_id, run_id):
                return None
            expected_projection = _stop_projection(event)
        elif (
            prior.get("state") == "planned"
            and prior.get("next_action") == {"action": "prepare_release_run"}
        ):
            transition, projection = _prepared_after(plan_id, run_id, prior)
            if raw + b"\n" != transition:
                return None
            expected_projection = projection
        elif (
            prior.get("state") == "prepared"
            and prior.get("next_action") == {"action": "finalize_release_preparation_evidence"}
        ):
            manifest_id = event.get("prerequisite_evidence_manifest_id")
            handoff_id = event.get("prerequisite_phase_handoff_id")
            if (
                not isinstance(manifest_id, str)
                or not isinstance(handoff_id, str)
                or not _digest_identity(manifest_id)
                or not _digest_identity(handoff_id)
            ):
                return None
            transition, projection = _finalized_after(plan_id, run_id, prior, manifest_id, handoff_id)
            if raw + b"\n" != transition:
                return None
            expected_projection = projection
        else:
            return None
        events.append(event)

    if expected_projection is None:
        return None
    return events, expected_projection, prior_projection


class Authority:
    def __init__(self, root: str, relative_output: str) -> None:
        self.output_fd, self.descriptors, self.components = _open_parent(root, relative_output)
        root_components = [component for component in root.split("/") if component]
        self.root_fd = self.descriptors[len(root_components)]
        self.runs_fd: int | None = None
        self.run_fd: int | None = None

    def linked(self) -> bool:
        return _chain_still_linked(self.descriptors, self.components)

    def append_directory(self, parent_fd: int, name: str) -> int:
        descriptor = os.open(name, _directory_flags(), dir_fd=parent_fd)
        self.descriptors.append(descriptor)
        self.components.append(name)
        return descriptor

    def close(self) -> None:
        for descriptor in reversed(self.descriptors):
            try:
                os.close(descriptor)
            except OSError:
                pass


def _name_matches_descriptor(parent_fd: int, name: str, descriptor: int) -> bool:
    expected = os.fstat(descriptor)
    try:
        visible = os.stat(name, dir_fd=parent_fd, follow_symlinks=False)
    except OSError:
        return False
    return (
        stat.S_ISREG(visible.st_mode)
        and visible.st_dev == expected.st_dev
        and visible.st_ino == expected.st_ino
    )


def _replace_opened_name(parent_fd: int, name: str, replacement: str) -> None:
    held = name + ".held"
    os.rename(name, held, src_dir_fd=parent_fd, dst_dir_fd=parent_fd)
    if replacement:
        os.symlink(replacement, name, dir_fd=parent_fd)
        return
    descriptor = os.open(
        name,
        os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW | os.O_CLOEXEC,
        0o600,
        dir_fd=parent_fd,
    )
    os.close(descriptor)


def _read_regular(
    parent_fd: int,
    name: str,
    fault: str = "",
    replacement: str = "",
) -> bytes | None:
    descriptor: int | None = None
    try:
        descriptor = os.open(name, os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC, dir_fd=parent_fd)
        identity = os.fstat(descriptor)
        if not stat.S_ISREG(identity.st_mode) or identity.st_size > MAX_STATE_BYTES:
            raise OSError("not a bounded regular file")
        growth_fault = {
            "binding.json": "binding_growth_after_open",
            "history.jsonl": "history_growth_after_open",
            "projection.json": "projection_growth_after_open",
        }.get(name)
        if fault == growth_fault:
            writer = os.open(name, os.O_WRONLY | os.O_NOFOLLOW | os.O_CLOEXEC, dir_fd=parent_fd)
            try:
                os.ftruncate(writer, MAX_STATE_BYTES + 1)
            finally:
                os.close(writer)
        chunks: list[bytes] = []
        remaining = MAX_STATE_BYTES + 1
        while remaining > 0:
            chunk = os.read(descriptor, min(65536, remaining))
            if not chunk:
                break
            chunks.append(chunk)
            remaining -= len(chunk)
        if MAX_STATE_BYTES + 1 - remaining > MAX_STATE_BYTES or os.read(descriptor, 1):
            return None
        matching_fault = {
            "binding.json": {"read_identity_after_open", "binding_identity_after_read"},
            "history.jsonl": {"history_identity_after_read"},
            "projection.json": {"projection_identity_after_read"},
        }.get(name, set())
        if fault == "manifest_unlink_after_read" and name.endswith(".evidence-manifest.json"):
            os.unlink(name, dir_fd=parent_fd)
        if fault == "handoff_substitute_after_read" and name.endswith(".phase-handoff.json"):
            _replace_opened_name(parent_fd, name, replacement)
        if fault in matching_fault:
            _replace_opened_name(parent_fd, name, replacement)
        if not _name_matches_descriptor(parent_fd, name, descriptor):
            return None
        return b"".join(chunks)
    except OSError:
        return None
    finally:
        if descriptor is not None:
            os.close(descriptor)


def _write_all(descriptor: int, contents: bytes, limit: int | None = None) -> bool:
    expected = len(contents)
    maximum = expected if limit is None else min(expected, limit)
    written = 0
    while written < maximum:
        count = os.write(descriptor, contents[written:maximum])
        if count <= 0:
            return False
        written += count
    return written == expected


def _replace_run_path(root: str, relative_output: str, run_id: str, replacement: str) -> None:
    run_path = os.path.join(root, *relative_output.split("/"), "runs", run_id)
    held = run_path + ".held"
    os.rename(run_path, held)
    os.symlink(replacement, run_path)


def _publish(
    authority: Authority,
    filename: str,
    contents: bytes,
    fault: str,
    kind: str,
    root: str,
    relative_output: str,
    run_id: str,
    replacement: str,
) -> bool:
    assert authority.run_fd is not None
    try:
        target = os.stat(filename, dir_fd=authority.run_fd, follow_symlinks=False)
        if not stat.S_ISREG(target.st_mode):
            return False
    except FileNotFoundError:
        pass

    if fault == "replace_run_before_state_stage" and kind == "history":
        _replace_run_path(root, relative_output, run_id, replacement)

    if not authority.linked():
        return False

    if fault == "history_target_symlink" and kind == "history":
        os.symlink("binding.json", filename, dir_fd=authority.run_fd)
        return False
    if fault == "projection_target_symlink" and kind == "projection":
        os.symlink("binding.json", filename, dir_fd=authority.run_fd)
        return False

    stage = ".state-" + secrets.token_hex(32)
    descriptor: int | None = None
    try:
        if fault == "open" or fault == "projection_stage" and kind == "projection":
            return False
        descriptor = os.open(
            stage,
            os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW | os.O_CLOEXEC,
            0o600,
            dir_fd=authority.run_fd,
        )
        limit = max(1, len(contents) // 2) if fault in {"write", "append_short"} and kind == "history" else None
        if not _write_all(descriptor, contents, limit):
            return False
        os.fsync(descriptor)
        os.close(descriptor)
        descriptor = None
        os.fsync(authority.run_fd)

        if not authority.linked() or fault == "state_publish":
            return False
        if fault in {"projection_publish", "prepared_projection"} and kind == "projection":
            return False

        if fault == "replace_run_after_link_before_state_publish" and kind == "history":
            _replace_run_path(root, relative_output, run_id, replacement)
        os.replace(stage, filename, src_dir_fd=authority.run_fd, dst_dir_fd=authority.run_fd)
        os.fsync(authority.run_fd)
        return authority.linked()
    finally:
        if descriptor is not None:
            os.close(descriptor)
        try:
            os.unlink(stage, dir_fd=authority.run_fd)
        except OSError:
            pass


def _binding_bytes(authority: Authority, plan_id: str, run_id: str) -> bytes:
    assert authority.run_fd is not None
    root = os.fstat(authority.root_fd)
    output = os.fstat(authority.output_fd)
    run = os.fstat(authority.run_fd)
    return _canonical({
        "schema_version": "fight-common.release-run-binding/v1",
        "plan_id": plan_id,
        "run_id": run_id,
        "directory_device": run.st_dev,
        "directory_inode": run.st_ino,
        "runs_root_device": root.st_dev,
        "runs_root_inode": root.st_ino,
        "output_device": output.st_dev,
        "output_inode": output.st_ino,
    })


def _open_existing(
    root: str,
    relative_output: str,
    plan_id: str,
    run_id: str,
    fault: str = "",
    replacement: str = "",
) -> tuple[Authority | None, str]:
    authority: Authority | None = None
    try:
        authority = Authority(root, relative_output)
        authority.runs_fd = authority.append_directory(authority.output_fd, "runs")
        authority.run_fd = authority.append_directory(authority.runs_fd, run_id)
        if not authority.linked():
            raise OSError("authority was replaced")
        binding = _read_regular(authority.run_fd, "binding.json", fault, replacement)
        if binding is None:
            raise OSError("binding is missing")
        decoded = json.loads(binding)
        if not isinstance(decoded, dict) or binding != _binding_bytes(
            authority,
            str(decoded.get("plan_id", "")),
            str(decoded.get("run_id", "")),
        ):
            raise OSError("binding is malformed")
        if decoded.get("run_id") != run_id:
            raise OSError("run binding differs")
        if decoded.get("plan_id") != plan_id:
            return authority, "stale"
        return authority, "verified"
    except FileNotFoundError:
        if authority is not None:
            authority.close()
        return None, "missing"
    except (OSError, ValueError, json.JSONDecodeError):
        if authority is not None:
            authority.close()
        return None, INDETERMINATE


def _lock(authority: Authority, fault: str, replacement: str = "") -> tuple[int | None, str]:
    assert authority.run_fd is not None
    if fault in {"writer_open", "writer_native_open", "writer_directory_mismatch_before_open"}:
        return None, INDETERMINATE
    try:
        descriptor = os.open(
            ".writer.lock",
            os.O_RDWR | os.O_CREAT | os.O_NOFOLLOW | os.O_CLOEXEC,
            0o600,
            dir_fd=authority.run_fd,
        )
        if not stat.S_ISREG(os.fstat(descriptor).st_mode):
            os.close(descriptor)
            return None, INDETERMINATE
        if fault == "writer_lock_identity_after_open":
            _replace_opened_name(authority.run_fd, ".writer.lock", replacement)
        if not _name_matches_descriptor(authority.run_fd, ".writer.lock", descriptor):
            os.close(descriptor)
            return None, INDETERMINATE
        try:
            fcntl.flock(descriptor, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            os.close(descriptor)
            return None, "conflict"
        if fault == "writer_lock":
            os.close(descriptor)
            return None, "conflict"
        if (
            fault == "writer_directory_after_lock"
            or not _name_matches_descriptor(authority.run_fd, ".writer.lock", descriptor)
            or not authority.linked()
        ):
            os.close(descriptor)
            return None, INDETERMINATE
        return descriptor, "acquired"
    except OSError:
        return None, INDETERMINATE


def _paths(root: str, relative_output: str, run_id: str) -> tuple[str, str]:
    base = os.path.join(root, *relative_output.split("/"), "runs", run_id)
    return base + "/history.jsonl", base + "/projection.json"


def create(root: str, relative_output: str, plan_id: str, run_id: str, fault: str) -> dict[str, Any]:
    authority: Authority | None = None
    lock_fd: int | None = None
    try:
        authority = Authority(root, relative_output)
        if fault in {"canonical_authority_identity", "runs_directory"} or not authority.linked():
            return {"status": FAILURE}
        try:
            os.mkdir("runs", 0o700, dir_fd=authority.output_fd)
            os.fsync(authority.output_fd)
        except FileExistsError:
            pass
        if fault == "runs_native_create":
            return {"status": FAILURE}
        authority.runs_fd = authority.append_directory(authority.output_fd, "runs")
        if fault == "runs_identity_after_create":
            return {"status": FAILURE}
        try:
            os.mkdir(run_id, 0o700, dir_fd=authority.runs_fd)
            os.fsync(authority.runs_fd)
        except FileExistsError:
            existing = os.stat(run_id, dir_fd=authority.runs_fd, follow_symlinks=False)
            return {"status": "conflict" if stat.S_ISDIR(existing.st_mode) else FAILURE}
        authority.run_fd = authority.append_directory(authority.runs_fd, run_id)
        if fault == "run_identity_after_create":
            return {"status": INDETERMINATE}
        if fault in {"runs_parent_directory_sync", "run_parent_directory_sync"}:
            return {"status": INDETERMINATE}
        if fault == "interrupt_before_binding":
            return {"crash": True}
        if fault == "binding_collision":
            descriptor = os.open("binding.json", os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600, dir_fd=authority.run_fd)
            os.close(descriptor)
            return {"status": INDETERMINATE}
        descriptor = os.open(
            "binding.json",
            os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW | os.O_CLOEXEC,
            0o600,
            dir_fd=authority.run_fd,
        )
        binding = _binding_bytes(authority, plan_id, run_id)
        if not _write_all(descriptor, binding):
            os.close(descriptor)
            return {"status": INDETERMINATE}
        os.fsync(descriptor)
        os.close(descriptor)
        os.fsync(authority.run_fd)
        lock_fd, lock_status = _lock(authority, fault)
        if lock_fd is None:
            return {"status": lock_status}
        if fault == "append_lock":
            return {"status": INDETERMINATE}
        planned_history, planned_projection = _planned(plan_id, run_id)
        if not _publish(authority, "history.jsonl", planned_history, fault, "history", root, relative_output, run_id, ""):
            return {"status": INDETERMINATE}
        if not _publish(authority, "projection.json", planned_projection, fault, "projection", root, relative_output, run_id, ""):
            return {"status": INDETERMINATE}
        history_path, projection_path = _paths(root, relative_output, run_id)
        prepared_history, prepared_projection, _ = _prepared(plan_id, run_id)
        return {
            "status": "planned",
            "history_path": history_path,
            "projection_path": projection_path,
            "sequence": 1,
            "state": "planned",
            "prepared_history_sha256": hashlib.sha256(prepared_history).hexdigest(),
            "prepared_projection_sha256": hashlib.sha256(prepared_projection).hexdigest(),
        }
    except (OSError, ValueError):
        return {"status": FAILURE}
    finally:
        if lock_fd is not None:
            os.close(lock_fd)
        if authority is not None:
            authority.close()


def publish(
    root: str,
    relative_output: str,
    plan_id: str,
    run_id: str,
    expected_sequence: int,
    expected_state: str,
    fault: str,
    replacement: str,
) -> dict[str, Any]:
    authority, status = _open_existing(root, relative_output, plan_id, run_id, fault, replacement)
    if authority is None or status != "verified":
        return {"status": INDETERMINATE if status == "missing" else status}
    lock_fd: int | None = None
    try:
        lock_fd, lock_status = _lock(authority, fault, replacement)
        if lock_fd is None:
            return {"status": lock_status}
        if fault == "append_lock":
            return {"status": INDETERMINATE}
        history = _read_regular(authority.run_fd, "history.jsonl", fault, replacement)
        projection = _read_regular(authority.run_fd, "projection.json", fault, replacement)
        validated = _validate_history(history, plan_id, run_id) if isinstance(history, bytes) else None
        if validated is None:
            return {"status": INDETERMINATE}
        events, expected_projection, _ = validated
        prior = events[-1]
        if prior.get("sequence") != expected_sequence or prior.get("state") != expected_state:
            return {"status": "advanced"}
        if (
            projection != expected_projection
            or prior.get("state") != "planned"
            or prior.get("next_action") != {"action": "prepare_release_run"}
        ):
            return {"status": INDETERMINATE}
        transition, prepared_projection = _prepared_after(plan_id, run_id, prior)
        prepared_history = history + transition
        if not _publish(authority, "history.jsonl", prepared_history, fault, "history", root, relative_output, run_id, replacement):
            return {"status": INDETERMINATE}
        if fault == "interrupt_run_projection":
            return {"crash": True}
        if not _publish(authority, "projection.json", prepared_projection, fault, "projection", root, relative_output, run_id, replacement):
            return {"status": INDETERMINATE}
        if fault == "prepared_projection_directory_sync":
            return {"status": INDETERMINATE}
        history_path, projection_path = _paths(root, relative_output, run_id)
        return {
            "status": "created",
            "history_path": history_path,
            "projection_path": projection_path,
            "sequence": prior["sequence"] + 1,
            "state": "prepared",
            "history_sha256": hashlib.sha256(prepared_history).hexdigest(),
            "projection_sha256": hashlib.sha256(prepared_projection).hexdigest(),
        }
    finally:
        if lock_fd is not None:
            os.close(lock_fd)
        authority.close()


def finalize(root: str, relative_output: str, plan_id: str, run_id: str, manifest_id: str, handoff_id: str, expected_sequence: int, expected_state: str, fault: str, replacement: str) -> dict[str, Any]:
    authority, status = _open_existing(root, relative_output, plan_id, run_id, fault, replacement)
    if authority is None or status != "verified":
        return {"status": INDETERMINATE if status == "missing" else status}
    lock_fd: int | None = None
    proof: ArtifactProof | None = None
    try:
        lock_fd, lock_status = _lock(authority, fault, replacement)
        if lock_fd is None:
            return {"status": lock_status}
        history = _read_regular(authority.run_fd, "history.jsonl", fault, replacement)
        projection = _read_regular(authority.run_fd, "projection.json", fault, replacement)
        validated = _validate_history(history, plan_id, run_id) if isinstance(history, bytes) else None
        if validated is None:
            return {"status": INDETERMINATE}
        events, expected_projection, _ = validated
        prior = events[-1]
        if prior.get("sequence") != expected_sequence or prior.get("state") != expected_state:
            return {"status": "advanced"}
        if (
            projection != expected_projection
            or prior.get("state") != "prepared"
            or prior.get("next_action") != {"action": "finalize_release_preparation_evidence"}
        ):
            return {"status": INDETERMINATE}
        manifest_name = manifest_id + ".evidence-manifest.json"
        handoff_name = handoff_id + ".phase-handoff.json"
        proof = _pin_finalization_artifacts(
            authority, plan_id, run_id, manifest_id, handoff_id, history, projection
        )
        _inject_artifact_publication_fault(authority.output_fd, fault, manifest_name, handoff_name, replacement)
        if proof is None or not proof.valid():
            return {"status": INDETERMINATE}
        transition, final_projection = _finalized_after(plan_id, run_id, prior, manifest_id, handoff_id)
        final_history = history + transition
        failed = json.loads(transition)
        if not _publish(authority, "history.jsonl", final_history, fault, "history", root, relative_output, run_id, ""):
            return {"status": INDETERMINATE}
        _inject_artifact_postpublication_fault(
            authority.output_fd, fault, manifest_name, handoff_name, replacement
        )
        if not proof.valid():
            _compensate_publication(
                authority, final_history, failed, prior, root, relative_output, run_id
            )
            return {"status": INDETERMINATE}
        if fault == "interrupt_finalized_projection":
            return {"crash": True}
        if not _publish(authority, "projection.json", final_projection, fault, "projection", root, relative_output, run_id, ""):
            return {"status": INDETERMINATE}
        if not proof.valid():
            _compensate_publication(
                authority, final_history, failed, prior, root, relative_output, run_id
            )
            return {"status": INDETERMINATE}
        history_path, projection_path = _paths(root, relative_output, run_id)
        return {
            "status": "created",
            "history_path": history_path,
            "projection_path": projection_path,
            "sequence": prior["sequence"] + 1,
            "state": "prepared",
            "history_sha256": hashlib.sha256(final_history).hexdigest(),
            "projection_sha256": hashlib.sha256(final_projection).hexdigest(),
            "prepared_history_sha256": hashlib.sha256(history).hexdigest(),
            "prepared_projection_sha256": hashlib.sha256(projection).hexdigest(),
            "prerequisite_evidence_manifest_id": manifest_id,
            "prerequisite_phase_handoff_id": handoff_id,
        }
    finally:
        if proof is not None:
            proof.close()
        if lock_fd is not None:
            os.close(lock_fd)
        authority.close()


def resume(root: str, relative_output: str, plan_id: str, run_id: str, fault: str, replacement: str) -> dict[str, Any]:
    authority, status = _open_existing(root, relative_output, plan_id, run_id, fault, replacement)
    if authority is None or status != "verified":
        return {"status": status}
    lock_fd: int | None = None
    proof: ArtifactProof | None = None
    try:
        lock_fd, lock_status = _lock(authority, fault, replacement)
        if lock_fd is None:
            return {"status": lock_status}
        if fault in {"resume_parent_directory_sync", "read_native_open"}:
            return {"status": INDETERMINATE}
        history = _read_regular(authority.run_fd, "history.jsonl", fault, replacement)
        projection = _read_regular(authority.run_fd, "projection.json", fault, replacement)
        validated = _validate_history(history, plan_id, run_id) if isinstance(history, bytes) else None
        if validated is not None:
            events, expected_projection, prior_projection = validated
            last = events[-1]
            projection_repaired = False
            if _is_stop_truth(last):
                manifest_id = last.get("evidence_manifest_id")
                handoff_id = last.get("phase_handoff_id")
                if (manifest_id is None) != (handoff_id is None):
                    return {"status": INDETERMINATE}
                if isinstance(manifest_id, str) and isinstance(handoff_id, str):
                    proof = _pin_artifacts(authority.output_fd, [
                        plan_id + ".json",
                        manifest_id + ".evidence-manifest.json",
                        handoff_id + ".phase-handoff.json",
                    ])
                    if (
                        proof is None
                        or not _stop_artifacts(
                            proof,
                            plan_id,
                            run_id,
                            str(last.get("stop_state", "")),
                            str(last.get("finding_id", "")),
                            str((last.get("next_action") or {}).get("action", "")),
                            manifest_id,
                            handoff_id,
                            "projection_bound",
                        )
                        or not proof.valid()
                    ):
                        return {"status": INDETERMINATE}
                elif not _artifactless_stop_allowed(
                    str(last.get("stop_code", "")),
                    str(last.get("stop_state", "")),
                    str(last.get("finding_id", "")),
                    str((last.get("next_action") or {}).get("action", "")),
                ):
                    return {"status": INDETERMINATE}
            if projection == prior_projection:
                if last.get("state") == "prepared" and last.get("next_action") == {"action": "package_release_run"}:
                    manifest_id = str(last.get("prerequisite_evidence_manifest_id", ""))
                    handoff_id = str(last.get("prerequisite_phase_handoff_id", ""))
                    finalization_inputs = _finalization_inputs(
                        history, events, plan_id, run_id, manifest_id, handoff_id
                    )
                    if finalization_inputs is None:
                        return {"status": INDETERMINATE}
                    prior_history, prior_projection = finalization_inputs
                    proof = _pin_finalization_artifacts(
                        authority, plan_id, run_id,
                        manifest_id,
                        handoff_id,
                        prior_history, prior_projection,
                    )
                    if proof is None or not proof.valid():
                        return {"status": INDETERMINATE}
                if not _publish(authority, "projection.json", expected_projection, fault, "projection", root, relative_output, run_id, ""):
                    return {"status": INDETERMINATE}
                if proof is not None and not proof.valid():
                    _publish(authority, "projection.json", projection, "", "projection", root, relative_output, run_id, "")
                    return {"status": INDETERMINATE}
                projection = expected_projection
                projection_repaired = True
            if _is_stop_truth(last) and projection == expected_projection:
                history_path, projection_path = _paths(root, relative_output, run_id)
                result = {
                    "status": "stopped",
                    "history_path": history_path,
                    "projection_path": projection_path,
                    "stop_code": last["stop_code"],
                    "stop_state": last["stop_state"],
                    "finding_id": last["finding_id"],
                    "next_action": last["next_action"]["action"],
                    "sequence": last["sequence"],
                    "state": last["state"],
                    "projection_repaired": projection_repaired,
                }
                if "resume_state" in last:
                    result["resume_state"] = last["resume_state"]
                    result["resume_sequence"] = last["resume_sequence"]
                    result["resume_next_action"] = last["resume_next_action"]["action"]
                if "evidence_manifest_id" in last:
                    result["prerequisite_evidence_manifest_id"] = last["evidence_manifest_id"]
                    result["prerequisite_phase_handoff_id"] = last["phase_handoff_id"]
                return result
            if projection != expected_projection:
                return {"status": INDETERMINATE}
            history_path, projection_path = _paths(root, relative_output, run_id)
            if last.get("state") == "planned" and last.get("next_action") == {"action": "prepare_release_run"}:
                transition, prepared_projection = _prepared_after(plan_id, run_id, last)
                return {
                    "status": "planned",
                    "history_path": history_path,
                    "projection_path": projection_path,
                    "sequence": last["sequence"],
                    "state": last["state"],
                    "projection_repaired": projection_repaired,
                    "prepared_history_sha256": hashlib.sha256(history + transition).hexdigest(),
                    "prepared_projection_sha256": hashlib.sha256(prepared_projection).hexdigest(),
                }
            if last.get("state") == "prepared" and last.get("next_action") == {"action": "finalize_release_preparation_evidence"}:
                return {
                    "status": "evidence_pending",
                    "history_path": history_path,
                    "projection_path": projection_path,
                    "sequence": last["sequence"],
                    "state": last["state"],
                    "projection_repaired": projection_repaired,
                    "history_sha256": hashlib.sha256(history).hexdigest(),
                    "projection_sha256": hashlib.sha256(projection).hexdigest(),
                }
            if last.get("state") == "prepared" and last.get("next_action") == {"action": "package_release_run"}:
                manifest_id = last.get("prerequisite_evidence_manifest_id")
                handoff_id = last.get("prerequisite_phase_handoff_id")
                if not isinstance(manifest_id, str) or not isinstance(handoff_id, str):
                    return {"status": INDETERMINATE}
                finalization_inputs = _finalization_inputs(
                    history, events, plan_id, run_id, manifest_id, handoff_id
                )
                if finalization_inputs is None:
                    return {"status": INDETERMINATE}
                prior_history, prior_projection = finalization_inputs
                proof = _pin_finalization_artifacts(
                    authority, plan_id, run_id, manifest_id, handoff_id, prior_history, prior_projection
                )
                if proof is None or not proof.valid():
                    return {"status": INDETERMINATE}
                return {
                    "status": "verified",
                    "history_path": history_path,
                    "projection_path": projection_path,
                    "sequence": last["sequence"],
                    "state": last["state"],
                    "projection_repaired": projection_repaired,
                    "history_sha256": hashlib.sha256(history).hexdigest(),
                    "projection_sha256": hashlib.sha256(projection).hexdigest(),
                    "prepared_history_sha256": hashlib.sha256(prior_history).hexdigest(),
                    "prepared_projection_sha256": hashlib.sha256(prior_projection).hexdigest(),
                    "prerequisite_evidence_manifest_id": manifest_id,
                    "prerequisite_phase_handoff_id": handoff_id,
                }
        if isinstance(history, bytes):
            try:
                state_plan_ids = [json.loads(line)["plan_id"] for line in history.rstrip(b"\n").split(b"\n")]
                projection_plan_id = json.loads(projection or b"{}").get("plan_id")
                if state_plan_ids and len(set(state_plan_ids)) == 1 and projection_plan_id == state_plan_ids[0] and state_plan_ids[0] != plan_id:
                    return {"status": "stale"}
            except (KeyError, TypeError, ValueError, json.JSONDecodeError):
                pass
        return {"status": INDETERMINATE}
    finally:
        if proof is not None:
            proof.close()
        if lock_fd is not None:
            os.close(lock_fd)
        authority.close()


def recover(
    root: str,
    relative_output: str,
    plan_id: str,
    run_id: str,
    stop_sequence: int,
    stop_code: str,
    stop_state: str,
    finding_id: str,
    next_action: str,
    repair_manifest_id: str,
    repair_handoff_id: str,
    fault: str,
    replacement: str,
) -> dict[str, Any]:
    authority, status = _open_existing(root, relative_output, plan_id, run_id, fault, replacement)
    if authority is None or status != "verified":
        return {"status": INDETERMINATE if status == "missing" else status}
    lock_fd: int | None = None
    proof: ArtifactProof | None = None
    try:
        lock_fd, lock_status = _lock(authority, fault, replacement)
        if lock_fd is None:
            return {"status": lock_status}
        history = _read_regular(authority.run_fd, "history.jsonl", fault, replacement)
        projection = _read_regular(authority.run_fd, "projection.json", fault, replacement)
        validated = _validate_history(history, plan_id, run_id) if isinstance(history, bytes) else None
        if validated is None:
            return {"status": INDETERMINATE}
        events, expected_projection, _ = validated
        stopped = events[-1]
        if (
            projection != expected_projection
            or not _is_stop_truth(stopped)
            or stopped.get("sequence") != stop_sequence
            or stopped.get("stop_code") != stop_code
            or stopped.get("stop_state") != stop_state
            or stopped.get("finding_id") != finding_id
            or stopped.get("next_action") != {"action": next_action}
            or not all(key in stopped for key in ("resume_state", "resume_sequence", "resume_next_action"))
        ):
            return {"status": INDETERMINATE}
        has_repair_pair = repair_manifest_id != "" and repair_handoff_id != ""
        if (repair_manifest_id == "") != (repair_handoff_id == ""):
            return {"status": INDETERMINATE}
        requires_repair_pair = next_action in {"repair_release_evidence_storage", "reconcile_named_release_run"}
        if requires_repair_pair != has_repair_pair:
            return {"status": INDETERMINATE}
        if has_repair_pair:
            manifest_name = repair_manifest_id + ".evidence-manifest.json"
            handoff_name = repair_handoff_id + ".phase-handoff.json"
            proof = _pin_artifacts(
                authority.output_fd,
                [plan_id + ".json", manifest_name, handoff_name],
            )
            if (not _digest_identity(repair_manifest_id)
                    or not _digest_identity(repair_handoff_id)
                    or proof is None
                    or not _stop_artifacts(
                        proof, plan_id, run_id, stop_state, finding_id, next_action,
                        repair_manifest_id, repair_handoff_id, "evidence_only",
                    )):
                return {"status": INDETERMINATE}
            _inject_artifact_publication_fault(authority.output_fd, fault, manifest_name, handoff_name, replacement)
            if not proof.valid():
                return {"status": INDETERMINATE}
        transition, recovered_projection = _recovered(plan_id, run_id, stopped)
        recovered_history = history + transition
        failed = json.loads(transition)
        if not _publish(authority, "history.jsonl", recovered_history, fault, "history", root, relative_output, run_id, replacement):
            return {"status": INDETERMINATE}
        if proof is not None:
            _inject_artifact_postpublication_fault(
                authority.output_fd, fault, manifest_name, handoff_name, replacement
            )
        if proof is not None and not proof.valid():
            _compensate_publication(
                authority, recovered_history, failed, stopped, root, relative_output, run_id
            )
            return {"status": INDETERMINATE}
        if not _publish(authority, "projection.json", recovered_projection, fault, "projection", root, relative_output, run_id, replacement):
            return {"status": INDETERMINATE}
        if proof is not None and not proof.valid():
            _compensate_publication(
                authority, recovered_history, failed, stopped, root, relative_output, run_id
            )
            return {"status": INDETERMINATE}
        history_path, projection_path = _paths(root, relative_output, run_id)
        return {
            "status": "created",
            "history_path": history_path,
            "projection_path": projection_path,
            "sequence": stopped["sequence"] + 1,
            "state": stopped["resume_state"],
            "next_action": stopped["resume_next_action"]["action"],
        }
    finally:
        if proof is not None:
            proof.close()
        if lock_fd is not None:
            os.close(lock_fd)
        authority.close()


def stop(
    root: str,
    relative_output: str,
    plan_id: str,
    run_id: str,
    stop_code: str,
    stop_state: str,
    finding_id: str,
    next_action: str,
    manifest_id: str,
    handoff_id: str,
    expected_sequence: int | None,
    expected_state: str | None,
    fault: str,
    replacement: str,
) -> dict[str, Any]:
    if not _stop_contract_allowed(stop_code, stop_state, finding_id, next_action):
        return {"status": INDETERMINATE}
    authority, status = _open_existing(root, relative_output, plan_id, run_id, fault, replacement)
    if authority is None and status == "missing":
        if expected_sequence is not None or expected_state is not None:
            return {"status": "advanced"}
        return create_stopped(
            root,
            relative_output,
            plan_id,
            run_id,
            stop_code,
            stop_state,
            finding_id,
            next_action,
            manifest_id,
            handoff_id,
            fault,
            replacement,
        )
    if authority is None or status != "verified":
        return {"status": status}
    lock_fd: int | None = None
    proof: ArtifactProof | None = None
    try:
        lock_fd, lock_status = _lock(authority, fault, replacement)
        if lock_fd is None:
            return {"status": lock_status}
        history = _read_regular(authority.run_fd, "history.jsonl", fault, replacement)
        projection_bytes = _read_regular(authority.run_fd, "projection.json", fault, replacement)
        if history is None or projection_bytes is None:
            return {"status": INDETERMINATE}
        validated = _validate_history(history, plan_id, run_id)
        if validated is None:
            return {"status": INDETERMINATE}
        events, expected_projection, _ = validated
        last = events[-1]
        if projection_bytes != expected_projection:
            return {"status": INDETERMINATE}
        history_path, projection_path = _paths(root, relative_output, run_id)
        if (
            last.get("operation") != "stop_release_preparation"
            and (
                expected_sequence is None
                or expected_state is None
                or last.get("sequence") != expected_sequence
                or last.get("state") != expected_state
            )
        ):
            return {"status": "advanced"}
        if manifest_id or handoff_id:
            if not manifest_id or not handoff_id or not _digest_identity(manifest_id) or not _digest_identity(handoff_id):
                return {"status": INDETERMINATE}
            manifest_name = manifest_id + ".evidence-manifest.json"
            handoff_name = handoff_id + ".phase-handoff.json"
            proof = _pin_artifacts(authority.output_fd, [plan_id + ".json", manifest_name, handoff_name])
            if (proof is None or not _stop_artifacts(
                proof, plan_id, run_id, stop_state, finding_id, next_action,
                manifest_id, handoff_id, "projection_bound",
            )):
                return {"status": INDETERMINATE}
            _inject_artifact_publication_fault(
                authority.output_fd, fault, manifest_name, handoff_name, replacement
            )
            if not proof.valid():
                return {"status": INDETERMINATE}
        elif not _artifactless_stop_allowed(stop_code, stop_state, finding_id, next_action):
            return {"status": INDETERMINATE}
        if last.get("operation") == "stop_release_preparation":
            exact = (
                last.get("stop_state") == stop_state
                and last.get("stop_code") == stop_code
                and
                last.get("finding_id") == finding_id
                and last.get("next_action") == {"action": next_action}
                and last.get("evidence_manifest_id", "") == manifest_id
                and last.get("phase_handoff_id", "") == handoff_id
            )
            return _stop_receipt(
                "verified", history_path, projection_path, stop_code, stop_state,
                finding_id, next_action, last["sequence"], manifest_id, handoff_id,
            ) if exact else {"status": INDETERMINATE}
        sequence = last["sequence"] + 1
        bindings: dict[str, str] = {}
        if manifest_id or handoff_id:
            if not manifest_id or not handoff_id or not _digest_identity(manifest_id) or not _digest_identity(handoff_id):
                return {"status": INDETERMINATE}
            bindings = {
                "evidence_manifest_id": manifest_id,
                "phase_handoff_id": handoff_id,
            }
        transition = _canonical({
            "sequence": sequence,
            "from": last["state"],
            "state": stop_state,
            "stop_code": stop_code,
            "stop_state": stop_state,
            "finding_id": finding_id,
            "operation": "stop_release_preparation",
            "plan_id": plan_id,
            "run_id": run_id,
            "resume_state": last["state"],
            "resume_sequence": last["sequence"],
            "resume_next_action": last["next_action"],
            **bindings,
            "next_action": {"action": next_action},
        })
        failed = json.loads(transition)
        stop_projection = _canonical({
            "schema_version": "fight-common.release-run-state/v1",
            "plan_id": plan_id,
            "run_id": run_id,
            "sequence": sequence,
            "state": stop_state,
            "stop_code": stop_code,
            "stop_state": stop_state,
            "finding_id": finding_id,
            "resume_state": last["state"],
            "resume_sequence": last["sequence"],
            "resume_next_action": last["next_action"],
            **bindings,
            "next_action": {"action": next_action},
        })
        if not _publish(authority, "history.jsonl", history + transition, fault, "history", root, relative_output, run_id, ""):
            return {"status": INDETERMINATE}
        if proof is not None:
            _inject_artifact_postpublication_fault(
                authority.output_fd, fault, manifest_name, handoff_name, replacement
            )
        if proof is not None and not proof.valid():
            _compensate_publication(
                authority, history + transition, failed, last, root, relative_output, run_id
            )
            return {"status": INDETERMINATE}
        if not _publish(authority, "projection.json", stop_projection, fault, "projection", root, relative_output, run_id, ""):
            return {"status": INDETERMINATE}
        if proof is not None and not proof.valid():
            _compensate_publication(
                authority, history + transition, failed, last, root, relative_output, run_id
            )
            return {"status": INDETERMINATE}
        return _stop_receipt(
            "created", history_path, projection_path, stop_code, stop_state,
            finding_id, next_action, sequence, manifest_id, handoff_id,
        )
    finally:
        if proof is not None:
            proof.close()
        if lock_fd is not None:
            os.close(lock_fd)
        authority.close()


def create_stopped(
    root: str,
    relative_output: str,
    plan_id: str,
    run_id: str,
    stop_code: str,
    stop_state: str,
    finding_id: str,
    next_action: str,
    manifest_id: str,
    handoff_id: str,
    fault: str,
    replacement: str,
) -> dict[str, Any]:
    if not _stop_contract_allowed(stop_code, stop_state, finding_id, next_action):
        return {"status": INDETERMINATE}
    authority: Authority | None = None
    lock_fd: int | None = None
    proof: ArtifactProof | None = None
    pending_name: str | None = None
    published = False
    try:
        authority = Authority(root, relative_output)
        if manifest_id or handoff_id:
            if not manifest_id or not handoff_id or not _digest_identity(manifest_id) or not _digest_identity(handoff_id):
                return {"status": INDETERMINATE}
            manifest_name = manifest_id + ".evidence-manifest.json"
            handoff_name = handoff_id + ".phase-handoff.json"
            proof = _pin_artifacts(authority.output_fd, [plan_id + ".json", manifest_name, handoff_name])
            if (proof is None or not _stop_artifacts(
                proof, plan_id, run_id, stop_state, finding_id, next_action,
                manifest_id, handoff_id, "projection_bound",
            )):
                return {"status": INDETERMINATE}
            _inject_artifact_publication_fault(
                authority.output_fd, fault, manifest_name, handoff_name, replacement
            )
            if not proof.valid():
                return {"status": INDETERMINATE}
        elif not _artifactless_stop_allowed(stop_code, stop_state, finding_id, next_action):
            return {"status": INDETERMINATE}
        try:
            os.mkdir("runs", 0o700, dir_fd=authority.output_fd)
            os.fsync(authority.output_fd)
        except FileExistsError:
            pass
        authority.runs_fd = authority.append_directory(authority.output_fd, "runs")
        try:
            os.stat(run_id, dir_fd=authority.runs_fd, follow_symlinks=False)
            return {"status": "conflict"}
        except FileNotFoundError:
            pass
        pending_name = ".pending-" + run_id + "-" + secrets.token_hex(8)
        try:
            os.mkdir(pending_name, 0o700, dir_fd=authority.runs_fd)
            os.fsync(authority.runs_fd)
        except FileExistsError:
            return {"status": "conflict"}
        authority.run_fd = authority.append_directory(authority.runs_fd, pending_name)
        descriptor = os.open(
            "binding.json",
            os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW | os.O_CLOEXEC,
            0o600,
            dir_fd=authority.run_fd,
        )
        if not _write_all(descriptor, _binding_bytes(authority, plan_id, run_id)):
            os.close(descriptor)
            return {"status": INDETERMINATE}
        os.fsync(descriptor)
        os.close(descriptor)
        os.fsync(authority.run_fd)
        lock_fd, lock_status = _lock(authority, fault)
        if lock_fd is None:
            return {"status": lock_status}
        bindings: dict[str, str] = {}
        if manifest_id or handoff_id:
            if not manifest_id or not handoff_id or not _digest_identity(manifest_id) or not _digest_identity(handoff_id):
                return {"status": INDETERMINATE}
            bindings = {
                "evidence_manifest_id": manifest_id,
                "phase_handoff_id": handoff_id,
            }
        transition = _canonical({
            "sequence": 1,
            "from": None,
            "state": stop_state,
            "stop_code": stop_code,
            "stop_state": stop_state,
            "finding_id": finding_id,
            "operation": "stop_release_preparation",
            "plan_id": plan_id,
            "run_id": run_id,
            **bindings,
            "next_action": {"action": next_action},
        })
        projection = _canonical({
            "schema_version": "fight-common.release-run-state/v1",
            "plan_id": plan_id,
            "run_id": run_id,
            "sequence": 1,
            "state": stop_state,
            "stop_code": stop_code,
            "stop_state": stop_state,
            "finding_id": finding_id,
            **bindings,
            "next_action": {"action": next_action},
        })
        if not _publish(authority, "history.jsonl", transition, fault, "history", root, relative_output, run_id, ""):
            return {"status": INDETERMINATE}
        if proof is not None:
            _inject_artifact_postpublication_fault(
                authority.output_fd, fault, manifest_name, handoff_name, replacement
            )
        if proof is not None and not proof.valid():
            return {"status": INDETERMINATE}
        if not _publish(authority, "projection.json", projection, fault, "projection", root, relative_output, run_id, ""):
            return {"status": INDETERMINATE}
        if proof is not None and not proof.valid():
            return {"status": INDETERMINATE}
        try:
            if fault == "stop_reveal_create_contention":
                os.mkdir(run_id, 0o700, dir_fd=authority.runs_fd)
                os.fsync(authority.runs_fd)
            if not authority.linked():
                return {"status": INDETERMINATE}
            if not _publish_run_directory_noreplace(authority.runs_fd, pending_name, run_id):
                return {"status": "conflict"}
            pending_name = run_id
            authority.components[-1] = run_id
            if not authority.linked():
                return {"status": INDETERMINATE}
            if fault == "stop_reveal_directory_sync":
                raise OSError("injected stopped-run reveal directory sync failure")
            os.fsync(authority.runs_fd)
            published = True
        except OSError:
            return {"status": INDETERMINATE}
        history_path, projection_path = _paths(root, relative_output, run_id)
        return _stop_receipt(
            "created", history_path, projection_path, stop_code, stop_state,
            finding_id, next_action, 1, manifest_id, handoff_id,
        )
    except (OSError, ValueError):
        return {"status": INDETERMINATE}
    finally:
        if proof is not None:
            proof.close()
        if authority is not None and pending_name is not None and not published:
            _quarantine_new_run(authority, pending_name)
        if lock_fd is not None:
            os.close(lock_fd)
        if authority is not None:
            authority.close()


def main(arguments: list[str]) -> int:
    if len(arguments) < 6:
        return 20
    action, root, relative_output, plan_id, run_id = arguments[1:6]
    extras = arguments[6:]
    if not _digest_identity(plan_id) or not _digest_identity(run_id):
        result = {"status": FAILURE if action == "create" else INDETERMINATE}
    else:
        if action in {"create", "resume"} and len(extras) == 2:
            fault, replacement = extras
        else:
            fault, replacement = "", ""
        if action == "create" and len(extras) == 2:
            result = create(root, relative_output, plan_id, run_id, fault)
        elif action == "publish" and len(extras) == 4:
            raw_sequence, expected_state, fault, replacement = extras
            if not raw_sequence.isascii() or not raw_sequence.isdecimal() or raw_sequence.startswith("0") or expected_state == "":
                result = {"status": INDETERMINATE}
            else:
                result = publish(root, relative_output, plan_id, run_id, int(raw_sequence), expected_state, fault, replacement)
        elif action == "resume" and len(extras) == 2:
            result = resume(root, relative_output, plan_id, run_id, fault, replacement)
        elif action == "recover" and len(extras) == 9:
            raw_sequence, stop_code, stop_state, finding_id, next_action, repair_manifest_id, repair_handoff_id, fault, replacement = extras
            if not raw_sequence.isascii() or not raw_sequence.isdecimal() or raw_sequence.startswith("0"):
                result = {"status": INDETERMINATE}
            else:
                result = recover(
                    root,
                    relative_output,
                    plan_id,
                    run_id,
                    int(raw_sequence),
                    stop_code,
                    stop_state,
                    finding_id,
                    next_action,
                    repair_manifest_id,
                    repair_handoff_id,
                    fault,
                    replacement,
                )
        elif action == "finalize" and len(extras) == 6:
            manifest_id, handoff_id = extras[:2]
            raw_sequence, expected_state, fault, replacement = extras[2:]
            if (not _digest_identity(manifest_id) or not _digest_identity(handoff_id)
                    or not raw_sequence.isascii() or not raw_sequence.isdecimal()
                    or raw_sequence.startswith("0") or expected_state == ""):
                result = {"status": INDETERMINATE}
            else:
                result = finalize(
                    root,
                    relative_output,
                    plan_id,
                    run_id,
                    manifest_id,
                    handoff_id,
                    int(raw_sequence),
                    expected_state,
                    fault,
                    replacement,
                )
        elif action == "stop" and len(extras) == 10:
            (
                stop_code,
                stop_state,
                finding_id,
                next_action,
                manifest_id,
                handoff_id,
                raw_expected_sequence,
                expected_state,
                fault,
                replacement,
            ) = extras
            if raw_expected_sequence == "" and expected_state == "":
                expected_sequence = None
                expected_state_value = None
            elif (
                not raw_expected_sequence.isascii()
                or not raw_expected_sequence.isdecimal()
                or raw_expected_sequence.startswith("0")
                or expected_state == ""
            ):
                result = {"status": INDETERMINATE}
                sys.stdout.write(json.dumps(result, separators=(",", ":")))
                return 0
            else:
                expected_sequence = int(raw_expected_sequence)
                expected_state_value = expected_state
            result = stop(
                root,
                relative_output,
                plan_id,
                run_id,
                stop_code,
                stop_state,
                finding_id,
                next_action,
                manifest_id,
                handoff_id,
                expected_sequence,
                expected_state_value,
                fault,
                replacement,
            )
        else:
            return 20
    sys.stdout.write(json.dumps(result, separators=(",", ":")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
