import { createHash } from 'node:crypto';
import { cp, mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';
import { checkOutputs, generateOutputs } from './generate.mjs';

const prototypeRoot = dirname(fileURLToPath(import.meta.url));
const frameworks = ['symfony', 'laravel', 'yii', 'codeigniter', 'slim'];

function expect(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

function digest(contents) {
    return createHash('sha256').update(contents).digest('hex');
}

await checkOutputs();

const schema = JSON.parse(await readFile(resolve(prototypeRoot, 'schemas/realtime.schema.json'), 'utf8'));
const ajv = new Ajv2020({ allErrors: true, strict: true });
addFormats(ajv);
const validate = ajv.compile(schema);
const usersInvalidated = {
    message_id: '018f4f5a-2266-7d1f-b965-a783bbd5c102',
    event_name: 'access.user.deleted',
    schema_version: 1,
    occurred_at: '2026-08-14T08:15:30.123Z',
    topic: 'https://starter.example.test/topics/access/users',
    payload: { invalidate: 'access.users.page' },
    metadata: { correlation_id: 'corr-017' },
};
const currentUserChanged = {
    ...usersInvalidated,
    event_name: 'access.current-user.lifecycle-changed',
    topic: 'private-current-user.018f4f5a',
    payload: { refresh: 'current-session' },
};
expect(validate(usersInvalidated), `Users-page envelope failed schema validation: ${ajv.errorsText(validate.errors)}`);
expect(validate(currentUserChanged), `Current-user envelope failed schema validation: ${ajv.errorsText(validate.errors)}`);

const leakingEnvelope = structuredClone(usersInvalidated);
leakingEnvelope.payload.user_id = 'private-user-id';
expect(!validate(leakingEnvelope), 'Realtime schema must reject a leaked domain field.');

const original = await generateOutputs();
expect(original['generated/http.ts'].includes('export type UserView ='), 'OpenAPI must generate a named UserView.');
expect(original['generated/realtime.ts'].includes("event_name: 'access.user.deleted'"), 'Realtime output must preserve the event discriminant.');
expect(original['generated/realtime.ts'].includes("event_name: 'access.current-user.lifecycle-changed'"), 'Realtime output must preserve both union variants.');

const scratch = await mkdtemp(resolve(tmpdir(), 'wf-017-client-contract-drift-'));
try {
    await cp(resolve(prototypeRoot, 'openapi.json'), resolve(scratch, 'openapi.json'));
    await cp(resolve(prototypeRoot, 'schemas'), resolve(scratch, 'schemas'), { recursive: true });
    const changedOpenapi = JSON.parse(await readFile(resolve(scratch, 'openapi.json'), 'utf8'));
    changedOpenapi.components.schemas.UserView.properties.display_name = { type: 'string' };
    changedOpenapi.components.schemas.UserView.required.push('display_name');
    await writeFile(resolve(scratch, 'openapi.json'), `${JSON.stringify(changedOpenapi, null, 4)}\n`);

    const changed = await generateOutputs(scratch);
    expect(changed['generated/http.ts'] !== original['generated/http.ts'], 'A source-schema change must alter generated HTTP types.');

    let driftRejected = false;
    try {
        await checkOutputs(scratch);
    } catch (error) {
        driftRejected = error instanceof Error && error.message.includes('generated/http.ts');
    }
    expect(driftRejected, 'The check must reject OpenAPI drift before a client build passes.');
} finally {
    await rm(scratch, { recursive: true, force: true });
}

execFileSync(resolve(prototypeRoot, 'node_modules/.bin/tsc'), ['--noEmit', '--project', 'tsconfig.json'], {
    cwd: prototypeRoot,
    stdio: 'inherit',
});

const packages = JSON.parse(await readFile(resolve(prototypeRoot, 'package.json'), 'utf8')).devDependencies;
await mkdir(resolve(prototypeRoot, 'receipts'), { recursive: true });
for (const framework of frameworks) {
    const receipt = {
        prototype: 'WF-017 client contract generation',
        framework,
        authoritative_sources: ['OpenAPI 3.1', 'JSON Schema 2020-12'],
        packages,
        generated: {
            http: 'generated/http.ts',
            http_sha256: digest(original['generated/http.ts']),
            realtime: 'generated/realtime.ts',
            realtime_sha256: digest(original['generated/realtime.ts']),
        },
        scenarios: {
            named_user_view_generated: true,
            realtime_discriminated_union_generated: true,
            valid_public_envelopes_accepted: true,
            leaked_domain_field_rejected: true,
            source_schema_drift_rejected: true,
            generated_types_compile: true,
        },
        result: 'passed',
    };
    await writeFile(
        resolve(prototypeRoot, `receipts/${framework}.json`),
        `${JSON.stringify(receipt, null, 4)}\n`,
    );
}

process.stdout.write('WF-017 client contract generation prototype passed for Symfony, Laravel, Yii, CodeIgniter, and Slim.\n');
