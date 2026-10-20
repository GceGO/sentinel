// Builds resources/dist/sentinel.css (Tailwind CSS v4, exactly like @tailwindcss/vite:
// compile -> scan sources -> build -> optimize) and copies resources/js/sentinel.js.
// Uses the Tailwind CSS v4 packages installed in the application or monorepo.
//
// Usage: node scripts/build-assets.mjs [--check]   (--check fails when dist is stale)
import { readFile, writeFile, mkdir } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { compile, optimize } from "@tailwindcss/node";
import { Scanner } from "@tailwindcss/oxide";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const entry = path.join(root, "resources/css/sentinel.css");
const base = path.dirname(entry);
const compiler = await compile(await readFile(entry, "utf8"), { base, onDependency: () => {} });
const sources = (compiler.root === "none" ? [] : compiler.root === null ? [{ base, pattern: "**/*", negated: false }] : [{ ...compiler.root, negated: false }]).concat(compiler.sources);
const css = optimize(compiler.build(new Scanner({ sources }).scan()), { minify: true }).code;
const js = await readFile(path.join(root, "resources/js/sentinel.js"), "utf8");
const dist = path.join(root, "resources/dist");

if (process.argv.includes("--check")) {
    const current = await Promise.all(["sentinel.css", "sentinel.js"].map((file) => readFile(path.join(dist, file), "utf8").catch(() => "")));
    const stale = current[0] !== css || current[1] !== js;
    process.stdout.write(JSON.stringify({ stale, cssBytes: css.length }));
    process.exit(stale ? 1 : 0);
}

await mkdir(dist, { recursive: true });
await writeFile(path.join(dist, "sentinel.css"), css);
await writeFile(path.join(dist, "sentinel.js"), js);
process.stdout.write(`sentinel.css ${css.length} bytes, sentinel.js ${js.length} bytes\n`);
