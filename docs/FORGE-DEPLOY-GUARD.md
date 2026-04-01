# Forge Deploy Guard

Use this guard to fail deployment early when built assets are missing.

## 1. Ensure Forge deploys the right branch

- Branch: `deploy`

## 2. Add this near the top of your Forge deploy script

```bash
cd /home/forge/kids-ai-story-builder-knbu0n31.on-forge.com/current
bash scripts/verify-build-artifacts.sh
```

If the manifest is missing or invalid, deploy fails immediately with a clear error.

## 3. Optional quick checks on server

```bash
ls -la public/build/manifest.json
grep -n '"resources/js/app.ts"' public/build/manifest.json
```
