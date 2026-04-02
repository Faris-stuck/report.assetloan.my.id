<?php

return [
    'agent_name' => getenv('AI_AGENT_NAME') ?: 'Hermes Agent',
    'endpoint' => getenv('AI_AGENT_ENDPOINT') ?: 'http://127.0.0.1:7860/api/v1/berli/chat',
    'api_key' => getenv('AI_AGENT_API_KEY') ?: 'replace-with-your-agent-api-key',
    'timeout' => (int) (getenv('AI_AGENT_TIMEOUT') ?: 25),
];