import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import openapiTS, { astToString } from 'openapi-typescript';
import { compile } from 'json-schema-to-typescript';

const prototypeRoot = dirname(fileURLToPath(import.meta.url));

async function readJson(path) {
    return JSON.parse(await readFile(path, 'utf8'));
}

export async function generateOutputs(sourceRoot = prototypeRoot) {
    const openapi = await readJson(resolve(sourceRoot, 'openapi.json'));
    const realtime = await readJson(resolve(sourceRoot, 'schemas/realtime.schema.json'));
    const httpAst = await openapiTS(openapi, {
        alphabetize: true,
        immutable: true,
        rootTypes: true,
        rootTypesNoSchemaPrefix: true,
    });

    return {
        'generated/http.ts': astToString(httpAst),
        'generated/realtime.ts': await compile(realtime, 'RealtimeEnvelope', {
            bannerComment: '/* Generated from schemas/realtime.schema.json. Do not edit. */',
            style: {
                bracketSpacing: true,
                printWidth: 100,
                semi: true,
                singleQuote: true,
                tabWidth: 4,
                trailingComma: 'all',
                useTabs: false,
            },
        }),
    };
}

export async function checkOutputs(sourceRoot = prototypeRoot) {
    const outputs = await generateOutputs(sourceRoot);
    const stale = [];
    for (const [relativePath, expected] of Object.entries(outputs)) {
        const actual = await readFile(resolve(prototypeRoot, relativePath), 'utf8').catch(() => '');
        if (actual !== expected) {
            stale.push(relativePath);
        }
    }

    if (stale.length > 0) {
        throw new Error(`Generated client contracts are stale: ${stale.join(', ')}`);
    }
}

async function main() {
    if (process.argv.includes('--check')) {
        await checkOutputs();
        process.stdout.write('Generated client contracts are current.\n');
        return;
    }

    const outputs = await generateOutputs();
    for (const [relativePath, contents] of Object.entries(outputs)) {
        await mkdir(dirname(resolve(prototypeRoot, relativePath)), { recursive: true });
        await writeFile(resolve(prototypeRoot, relativePath), contents);
    }
    process.stdout.write('Generated HTTP and realtime TypeScript contracts.\n');
}

if (resolve(process.argv[1] ?? '') === fileURLToPath(import.meta.url)) {
    await main();
}
