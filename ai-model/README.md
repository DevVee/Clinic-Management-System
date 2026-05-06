# AI Model Directory

Place your GGUF model file here. The system will automatically detect any `.gguf` file in this folder.

## Recommended Models (Low RAM / Windows)

| Model | Size | RAM Needed | Download |
|-------|------|-----------|---------|
| TinyLlama-1.1B-Chat | ~670 MB | ~1 GB | https://huggingface.co/TheBloke/TinyLlama-1.1B-Chat-v1.0-GGUF |
| Phi-2 (Q4) | ~1.6 GB | ~2 GB | https://huggingface.co/TheBloke/phi-2-GGUF |
| Mistral-7B (Q2) | ~2.8 GB | ~4 GB | https://huggingface.co/TheBloke/Mistral-7B-Instruct-v0.2-GGUF |

## Quick Setup (TinyLlama — recommended for low-end PCs)

1. Go to: https://huggingface.co/TheBloke/TinyLlama-1.1B-Chat-v1.0-GGUF/tree/main
2. Download: `tinyllama-1.1b-chat-v1.0.Q4_K_M.gguf`
3. Place the file in this folder (`/ai-model/`)
4. The system auto-detects it — no config needed

## File Naming

The PHP backend (`/api/ask.php`) uses `glob('*.gguf')` to find the first GGUF file.
You can rename the model file to anything as long as it ends in `.gguf`.

## Notes

- Do NOT commit model files to git (add `*.gguf` to `.gitignore`)
- One model file at a time is supported
- Larger models = better quality but slower and more RAM
