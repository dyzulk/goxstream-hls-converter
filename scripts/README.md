# Goxstream HLS Converter CLI (`ghc`) Scripts

This directory houses the source code and configuration for the Goxstream HLS Converter management CLI utility (`ghc`). The tool is designed to provide a unified, developer-friendly interface (resembling modern framework command-line helpers like Artisan or NestJS CLI) for managing a heterogeneous multi-package monorepo workspace.

---

## Directory Structure

```text
scripts/
├── ghc/
│   ├── src/
│   │   ├── commands/
│   │   │   ├── check.ts      # Verifies local tool availability (Go, PHP, FFmpeg, etc.)
│   │   │   ├── install.ts    # Installs JS workspace dependencies and PHP composer modules
│   │   │   ├── dev.ts        # Starts packages in development mode
│   │   │   ├── build.ts      # Compiles Go binary, Docker image, or JS workers
│   │   │   └── start.ts      # Runs compiled production targets
│   │   │
│   │   ├── utils/
│   │   │   ├── exec.ts       # Spawns child processes, forwarding I/O streams
│   │   │   └── checkEnv.ts   # Resolves executable path checks in the OS PATH
│   │   │
│   │   └── index.ts          # CLI main entrypoint (handles commander and interactive clack menus)
│   │
│   └── tsconfig.json         # Isolated TypeScript configurations for GHC development
│
└── README.md                 # This file
```

---

## How it Works

The utility operates in two modes depending on how it is invoked:

1. **Interactive Prompt Mode**:
   When run without any arguments (e.g. `./ghc` or `.\ghc.ps1`), the tool initiates an interactive, guided menu utilizing `@clack/prompts`. It allows developers to check system requirements, install package dependencies, or launch individual runners via a clean terminal selector.

2. **Direct Argument Mode**:
   When run with command arguments (e.g. `./ghc dev go-native` or `.\ghc.ps1 check`), the tool parses input utilizing `commander` and immediately runs the specified action, bypassing the selection prompt.

---

## Command Reference

| Command | Description | Runner Targets |
|---------|-------------|----------------|
| `check` | Validates if compiler/run tools are installed on host path | N/A |
| `install` | Automatically installs root pnpm packages and composer vendor packages | N/A |
| `dev [runner]` | Starts the selected package in hot-reload or local dev server mode | `cf-container`, `go-native`, `node-native`, `php-native`, `docker-native` |
| `build [runner]` | Compiles packages or builds local Docker containers | `cf-container`, `go-native`, `node-native`, `docker-native` |
| `start [runner]` | Runs compiled binaries or detached production containers | `cf-container`, `go-native`, `node-native`, `php-native`, `docker-native` |

---

## Execution Wrappers & Windows Compatibility

To run the utility seamlessly without typing verbose Node commands, two launcher scripts are placed at the workspace root:

* **`ghc` (Unix Shell Wrapper)**:
  Exposes the CLI on Linux/macOS. It maps parameters directly to Node and the tsx engine.
* **`ghc.ps1` (Windows PowerShell Wrapper)**:
  Exposes the CLI on Windows. On Windows, executing batch binaries (like `npx.cmd`) directly via PowerShell loses arguments due to PowerShell-to-batch stream conversions. To resolve this, `ghc.ps1` runs the Node executable directly against the `tsx` engine module in `node_modules/tsx/dist/cli.mjs`, ensuring command arguments (like `check` or `dev`) propagate correctly to the parser.

---

## TypeScript Path Aliasing

This module is fully type-safe. To make development and refactoring cleaner, all internal imports use the `@/` path prefix (e.g., `import { runCheck } from '@/commands/check'`). This is resolved by the `tsx` runtime dynamically using the `paths` and `baseUrl` mapping defined inside the root [tsconfig.json](file:///c:/Users/dyzulk/Documents/goxstream/goxstream-hls-converter/tsconfig.json).
