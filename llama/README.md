# llama.cpp Runtime Directory

Place the `llama-cli.exe` (and its required DLLs) in this folder.

## Download llama.cpp for Windows

### Option A — Pre-built release (easiest)

1. Go to: https://github.com/ggerganov/llama.cpp/releases/latest
2. Download the Windows build (e.g., `llama-bXXXX-bin-win-avx2-x64.zip`)
   - If your CPU supports AVX2 (most CPUs from 2013+): use `avx2`
   - Older CPU: use `avx` or `noavx`
3. Extract the zip into this folder (`/llama/`)
4. You need at minimum: `llama-cli.exe` and any `.dll` files it ships with

### Option B — Build from source

```bash
git clone https://github.com/ggerganov/llama.cpp
cd llama.cpp
cmake -B build
cmake --build build --config Release
# Copy build/bin/Release/llama-cli.exe here
```

## Verify Installation

Run from this directory:
```
llama-cli.exe --version
```

## Required Files Checklist

- [x] `llama-cli.exe` — main executable
- [x] Any `.dll` files (ggml.dll, llama.dll, etc.)

## Performance Flags Used by ask.php

| Flag | Value | Purpose |
|------|-------|---------|
| `--threads` | 2 | Limit CPU cores to avoid overheating |
| `-n` | 200 | Max tokens in response |
| `--temp` | 0.7 | Creativity/randomness (0=deterministic, 1=creative) |
| `--repeat-penalty` | 1.1 | Reduce repetition |
| `--ctx-size` | 512 | Context window size (keep small for low RAM) |

Adjust these values in `/api/ask.php` if needed.
