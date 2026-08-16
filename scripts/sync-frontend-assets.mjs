import { copyFile, mkdir, readFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const check = process.argv.includes("--check");
const assets = [
  ["node_modules/htmx.org/dist/htmx.min.js", "public/assets/htmx.min.js"],
  ["node_modules/htmx.org/LICENSE", "public/assets/htmx.LICENSE.txt"],
];

for (const [sourceName, targetName] of assets) {
  const source = resolve(root, sourceName);
  const target = resolve(root, targetName);

  if (check) {
    const [expected, actual] = await Promise.all([readFile(source), readFile(target)]);

    if (!expected.equals(actual)) {
      throw new Error(`${targetName} does not match the locked dependency.`);
    }

    continue;
  }

  await mkdir(dirname(target), { recursive: true });
  await copyFile(source, target);
}
