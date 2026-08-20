@php
    $aiRole = auth()->check() ? str_replace('_', ' ', auth()->user()->role) : 'pengunjung';
@endphp
<div id="laporin-ai-chat" class="ai-chat" data-ai-role="{{ $aiRole }}">
    <button type="button" class="ai-chat__launcher" id="ai-chat-launcher" aria-controls="ai-chat-panel" aria-expanded="false" aria-label="Buka AI Chat LAPORIN">
        <span aria-hidden="true">AI</span>
    </button>
    <section class="ai-chat__panel" id="ai-chat-panel" aria-labelledby="ai-chat-title" hidden>
        <header class="ai-chat__header">
            <div>
                <strong id="ai-chat-title">AI Chat LAPORIN</strong>
                <small>Mode {{ $aiRole }} · read-only</small>
            </div>
            <button type="button" class="ai-chat__close" id="ai-chat-close" aria-label="Tutup AI Chat">×</button>
        </header>
        <div class="ai-chat__messages" id="ai-chat-messages" role="log" aria-live="polite">
            <div class="ai-chat__bubble ai-chat__bubble--assistant">Halo. Saya AI Chat LAPORIN. Saya hanya membantu informasi, panduan, dan ringkasan yang sesuai kewenangan akun ini.</div>
        </div>
        <form class="ai-chat__form" id="ai-chat-form">
            <label class="visually-hidden" for="ai-chat-input">Pertanyaan untuk AI Chat</label>
            <textarea id="ai-chat-input" name="message" rows="2" maxlength="1000" placeholder="Tanyakan tentang LAPORIN…" autocomplete="off"></textarea>
            <button type="submit" id="ai-chat-send">Kirim</button>
        </form>
        <div class="ai-chat__hint">Jangan kirim password, token, kode akses, atau data rahasia.</div>
    </section>
</div>
<style>
.ai-chat{position:fixed;right:1rem;bottom:1rem;z-index:1080;font-family:inherit}.ai-chat__launcher{width:56px;height:56px;border:0;border-radius:50%;background:#0b6b4f;color:#fff;font-weight:800;box-shadow:0 10px 24px rgba(0,0,0,.18);cursor:pointer}.ai-chat__panel{width:min(380px,calc(100vw - 2rem));height:min(620px,calc(100vh - 7rem));background:#fff;border:1px solid rgba(0,0,0,.12);border-radius:18px;box-shadow:0 18px 48px rgba(0,0,0,.2);overflow:hidden;display:flex;flex-direction:column}.ai-chat__header{padding:.9rem 1rem;background:#0b6b4f;color:#fff;display:flex;align-items:center;justify-content:space-between}.ai-chat__header small{display:block;opacity:.8;font-size:.72rem;text-transform:capitalize}.ai-chat__close{border:0;background:transparent;color:#fff;font-size:1.7rem;line-height:1;cursor:pointer}.ai-chat__messages{flex:1;overflow:auto;padding:1rem;background:#f7faf8}.ai-chat__bubble{max-width:90%;padding:.7rem .85rem;border-radius:14px;margin-bottom:.65rem;line-height:1.45;font-size:.92rem;white-space:pre-wrap}.ai-chat__bubble--assistant{background:#fff;border:1px solid #dce8e2}.ai-chat__bubble--user{margin-left:auto;background:#0b6b4f;color:#fff}.ai-chat__sources{font-size:.72rem;opacity:.72;margin-top:-.2rem;margin-bottom:.7rem}.ai-chat__form{display:grid;grid-template-columns:1fr auto;gap:.5rem;padding:.8rem;border-top:1px solid #e7eee9}.ai-chat__form textarea{resize:none;border:1px solid #cddbd3;border-radius:12px;padding:.65rem .75rem;min-height:48px}.ai-chat__form button{border:0;border-radius:12px;padding:0 .9rem;background:#0b6b4f;color:#fff;font-weight:700}.ai-chat__hint{padding:0 .8rem .7rem;font-size:.68rem;color:#65746d}@media(max-width:576px){.ai-chat{right:.6rem;bottom:.6rem}.ai-chat__panel{width:calc(100vw - 1.2rem);height:calc(100vh - 5rem)}}
</style>
<script>
(() => {
    const launcher = document.getElementById('ai-chat-launcher');
    const panel = document.getElementById('ai-chat-panel');
    const close = document.getElementById('ai-chat-close');
    const form = document.getElementById('ai-chat-form');
    const input = document.getElementById('ai-chat-input');
    const messages = document.getElementById('ai-chat-messages');
    if (!launcher || !panel || !close || !form || !input || !messages) return;

    const addBubble = (text, kind = 'assistant', sources = []) => {
        const bubble = document.createElement('div');
        bubble.className = `ai-chat__bubble ai-chat__bubble--${kind}`;
        bubble.textContent = text;
        messages.appendChild(bubble);
        if (sources.length) {
            const source = document.createElement('div');
            source.className = 'ai-chat__sources';
            source.textContent = `Sumber: ${sources.join(', ')}`;
            messages.appendChild(source);
        }
        messages.scrollTop = messages.scrollHeight;
    };

    const open = () => { panel.hidden = false; launcher.setAttribute('aria-expanded', 'true'); input.focus(); };
    const hide = () => { panel.hidden = true; launcher.setAttribute('aria-expanded', 'false'); };
    launcher.addEventListener('click', () => panel.hidden ? open() : hide());
    close.addEventListener('click', hide);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || message.length > 1000) return;
        addBubble(message, 'user');
        input.value = '';
        input.disabled = true;
        form.querySelector('button').disabled = true;
        try {
            const response = await fetch('{{ route('ai.chat') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ message })
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                if (response.status === 419) {
                    throw new Error('Sesi keamanan halaman kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.');
                }
                if (response.status === 429) {
                    const retryAfter = response.headers.get('Retry-After');
                    throw new Error(retryAfter
                        ? `Batas permintaan AI tercapai. Silakan coba lagi dalam ${retryAfter} detik.`
                        : 'Batas permintaan AI tercapai. Silakan coba lagi beberapa saat lagi.');
                }
                if (response.status >= 500) {
                    throw new Error('Layanan AI sedang mengalami gangguan internal. Silakan coba lagi.');
                }
                throw new Error(payload.message || 'Permintaan tidak dapat diproses.');
            }
            addBubble(payload.answer || 'Maaf, saya belum dapat menjawab pertanyaan tersebut.', 'assistant', payload.sources || []);
        } catch (error) {
            addBubble(error instanceof Error ? error.message : 'Permintaan AI tidak dapat diproses. Silakan coba lagi.', 'assistant');
        } finally {
            input.disabled = false;
            form.querySelector('button').disabled = false;
            input.focus();
        }
    });
})();
</script>
