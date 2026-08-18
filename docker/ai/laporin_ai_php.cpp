#include <php.h>

#include <cstddef>
#include <cstring>

extern "C" int laporin_ai_generate(const char *prompt, char *output, size_t output_size);

PHP_FUNCTION(laporin_ai_generate_native)
{
    char *prompt = nullptr;
    size_t prompt_len = 0;

    ZEND_PARSE_PARAMETERS_START(1, 1)
        Z_PARAM_STRING(prompt, prompt_len)
    ZEND_PARSE_PARAMETERS_END();

    if (prompt == nullptr || prompt_len == 0 || prompt_len > 6000) {
        RETURN_NULL();
    }

    char output[8192];
    output[0] = '\0';

    const int result = laporin_ai_generate(prompt, output, sizeof(output));
    if (result != 0 || output[0] == '\0') {
        RETURN_NULL();
    }

    RETURN_STRINGL(output, std::strlen(output));
}

ZEND_BEGIN_ARG_WITH_RETURN_TYPE_INFO_EX(arginfo_laporin_ai_generate_native, 0, 1, IS_STRING, 1)
    ZEND_ARG_TYPE_INFO(0, prompt, IS_STRING, 0)
ZEND_END_ARG_INFO()

static const zend_function_entry laporin_ai_functions[] = {
    ZEND_FE(laporin_ai_generate_native, arginfo_laporin_ai_generate_native)
    ZEND_FE_END
};

zend_module_entry laporin_ai_module_entry = {
    STANDARD_MODULE_HEADER,
    "laporin_ai_native",
    laporin_ai_functions,
    nullptr,
    nullptr,
    nullptr,
    nullptr,
    nullptr,
    "1.0.0",
    STANDARD_MODULE_PROPERTIES
};

#ifdef COMPILE_DL_LAPORIN_AI_NATIVE
extern "C" {
ZEND_GET_MODULE(laporin_ai_module)
}
#endif
