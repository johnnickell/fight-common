# Supported Fight Common release lines

The fenced JSON block is the canonical support-policy data. The table below restates it for readers;
branch existence alone does not imply support. `ends_at` is an **exclusive UTC** boundary: an unfinished
patch stops at that instant unless a separately reviewed policy change extended it beforehand. Only
the latest patch release on a supported line is maintained. End-of-life tags and branches are preserved,
not deleted or repointed.

```json
{
  "schema_version": "fight-common.supported-versions/v1",
  "lines": [
    {
      "line": "1.0",
      "branch": "1.0",
      "initial_release": {"tag": "v1.0.0", "commit": "30422097c74501f6fdcb3cb8009e2c4797afd91a"},
      "latest_release": {"tag": "v1.0.0", "commit": "30422097c74501f6fdcb3cb8009e2c4797afd91a"},
      "phase": "limited",
      "allowed_fixes": ["security", "data-loss", "critical-compatibility"],
      "ends_at": "2026-12-05T00:00:00Z",
      "successor": "1.1"
    },
    {
      "line": "1.1",
      "branch": "1.1",
      "initial_release": {"tag": "1.1.0", "commit": "1666dbaa503e40ea2fc652bccbeba22e6cda6b70"},
      "latest_release": {"tag": "1.1.0", "commit": "1666dbaa503e40ea2fc652bccbeba22e6cda6b70"},
      "phase": "limited",
      "allowed_fixes": ["security", "data-loss", "critical-compatibility"],
      "ends_at": "2027-03-13T01:24:24Z",
      "successor": "1.2"
    },
    {
      "line": "1.2",
      "branch": "1.2",
      "initial_release": {"tag": "v1.2.0", "commit": "a2cd615d9b5064c9c30e994655536176249cd73b"},
      "latest_release": {"tag": "v1.2.0", "commit": "a2cd615d9b5064c9c30e994655536176249cd73b"},
      "phase": "limited",
      "allowed_fixes": ["security", "data-loss", "critical-compatibility"],
      "ends_at": "2027-04-05T05:41:42Z",
      "successor": "1.3"
    },
    {
      "line": "1.3",
      "branch": "1.3",
      "initial_release": {"tag": "v1.3.0", "commit": "7de6cad6e8a9752973ad9f8e27e285b0c1510582"},
      "latest_release": {"tag": "v1.3.0", "commit": "7de6cad6e8a9752973ad9f8e27e285b0c1510582"},
      "phase": "current",
      "allowed_fixes": ["bug", "security"],
      "ends_at": null,
      "successor": null
    }
  ]
}
```

| Line | Status after 1.3 publication | Exclusive UTC end | Latest release |
| --- | --- | --- | --- |
| 1.0 | Limited security, data-loss and critical-compatibility fixes | 2026-12-05 00:00:00 | `v1.0.0` |
| 1.1 | Limited security, data-loss and critical-compatibility fixes | 2027-03-13 01:24:24 | `1.1.0` |
| 1.2 | Limited security, data-loss and critical-compatibility fixes | 2027-04-05 05:41:42 | `v1.2.0` |
| 1.3 | Current: bug and security fixes | Not set until its successor publishes | `v1.3.0` |

The six-month clock for **each** superseded minor survives later minor releases: 1.0 does not become
end-of-life merely because 1.2 exists, nor 1.1 merely because 1.3 ships. The 1.1 deadline follows
the observed GitHub publication of `v1.2.0` at `2026-09-13T01:24:24Z`. There is no GitHub Release
for the legacy `1.1.0` tag, so its exact first-publication instant is unavailable. Its current
annotated tag carries a 2026-06-04 UTC tagger timestamp; to avoid shortening a June 4 six-month
promise, the 1.0 policy uses the end of the resulting December 4 UTC calendar day. This is a
declared support boundary, **not** proof of the actual tag-push/publication instant. If later
publication evidence appears, extend this boundary through a reviewed change before it expires.

The historical `v1.0.0` tag is lightweight. The authoritative published 1.1 baseline is the bare
annotated `1.1.0` tag (not the different lightweight `v1.1.0`). TASK-00101's authorship rewrite
changed its tag object to `7b8e53a14eba4fb12f1d49139ca25d649248e507` and peeled commit to
`1666dbaa503e40ea2fc652bccbeba22e6cda6b70`; older ADR/manifest identities describe the
pre-rewrite graph. The existing `1.1` branch does **not** point at that canonical release commit.
Supporting a line does not authorize patching from a mismatched base: reconcile the maintenance
branch, ancestry, review and signing requirements before attempting a 1.1 patch. Do not replace
published refs to accomplish that.

[`v1.3.0`](https://github.com/johnnickell/fight-common/releases/tag/v1.3.0) was published on GitHub at
`2026-10-05T05:41:42Z`. Its signed annotated tag peels to the exact `main` release merge recorded above;
the `1.3` maintenance branch starts at that same commit. The six-calendar-month limit on 1.2 ends
exclusively at `2027-04-05T05:41:42Z`. The 1.0 and 1.1 windows remain in force until their own
published boundaries and their branches are preserved, not removed early. A branch by itself is
not evidence that a line is supported. Support for multiple older minors during their own windows
does not waive the 1.x public API compatibility promise for current releases.
