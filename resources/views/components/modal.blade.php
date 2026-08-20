@props([
    'name',
    'show' => false,
    'maxWidth' => 'modal-lg'
])

@php
    $sizeClasses = [
        'sm' => 'modal-sm',
        'md' => 'modal-md',
        'lg' => 'modal-lg',
        'xl' => 'modal-xl',
        '2xl' => 'modal-lg',
        'modal-sm' => 'modal-sm',
        'modal-md' => 'modal-md',
        'modal-lg' => 'modal-lg',
        'modal-xl' => 'modal-xl',
    ][$maxWidth] ?? 'modal-lg';
@endphp

<div
    x-data="{
        show: @js($show),

        focusables() {
            const selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])';

            return [...$el.querySelectorAll(selector)]
                .filter(el => !el.hasAttribute('disabled'));
        },

        firstFocusable() {
            return this.focusables()[0];
        },

        lastFocusable() {
            return this.focusables().slice(-1)[0];
        },

        nextFocusable() {
            return this.focusables()[this.nextFocusableIndex()]
                || this.firstFocusable();
        },

        prevFocusable() {
            return this.focusables()[this.prevFocusableIndex()]
                || this.lastFocusable();
        },

        nextFocusableIndex() {
            const elements = this.focusables();

            if (elements.length === 0) {
                return 0;
            }

            return (
                elements.indexOf(document.activeElement) + 1
            ) % elements.length;
        },

        prevFocusableIndex() {
            const elements = this.focusables();

            if (elements.length === 0) {
                return 0;
            }

            const index = elements.indexOf(document.activeElement);

            return index <= 0
                ? elements.length - 1
                : index - 1;
        },

        closeModal() {
            this.show = false;
        }
    }"

    x-init="
        $watch('show', value => {
            if (value) {
                document.body.classList.add('modal-open');

                setTimeout(() => {
                    const element = firstFocusable();

                    if (element) {
                        element.focus();
                    }
                }, 100);
            } else {
                document.body.classList.remove('modal-open');
            }
        })
    "

    x-on:open-modal.window="
        if ($event.detail === '{{ $name }}') {
            show = true;
        }
    "

    x-on:close-modal.window="
        if ($event.detail === '{{ $name }}') {
            show = false;
        }
    "

    x-on:keydown.escape.window="
        if (show) {
            show = false;
        }
    "

    x-on:keydown.tab.prevent="
        if (show && focusables().length) {
            if ($event.shiftKey) {
                prevFocusable().focus();
            } else {
                nextFocusable().focus();
            }
        }
    "

    x-show="show"
    x-cloak

    class="laporin-modal"

    id="modal-{{ $name }}"

    role="dialog"
    aria-modal="true"

    style="display: none;"
>

    {{-- BACKDROP --}}
    <div
        class="laporin-modal-backdrop"
        x-on:click="closeModal()"
        aria-hidden="true"
    ></div>


    {{-- MODAL POSITION --}}
    <div
        class="laporin-modal-wrapper"
        x-on:click.self="closeModal()"
    >

        {{-- MODAL CONTENT --}}
        <div
            class="modal-dialog {{ $sizeClasses }} m-0"
            role="document"
        >

            <div class="modal-content">

                {{ $slot }}

            </div>

        </div>

    </div>

</div>