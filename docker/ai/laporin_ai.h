#define FFI_SCOPE "LAPORIN_AI"
#define FFI_LIB "/opt/laporin-ai/lib/liblaporin_ai.so"

typedef unsigned long size_t;

int laporin_ai_generate(const char *prompt, char *output, size_t output_size);
