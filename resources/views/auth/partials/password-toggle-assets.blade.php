@once
    <style>
        .password-control {
            position: relative;
        }

        .password-control .input {
            padding-right: 54px;
        }

        .password-control input::-ms-reveal,
        .password-control input::-ms-clear {
            display: none;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 4px;
            display: grid;
            width: 40px;
            height: 40px;
            transform: translateY(-50%);
            place-items: center;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #52606d;
            cursor: pointer;
        }

        .password-toggle:hover {
            background: #eef4f9;
            color: #15477A;
        }

        .password-toggle:focus-visible {
            outline: 3px solid rgba(241, 196, 15, .55);
            outline-offset: 1px;
        }

        .password-toggle-icon {
            width: 21px;
            height: 21px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .password-toggle-icon[hidden],
        .password-caps-warning[hidden] {
            display: none;
        }

        .password-caps-warning {
            margin: 0;
            color: #8a5b00;
            font-size: .82rem;
            font-weight: 800;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-password-control]').forEach(function (control) {
                const input = control.querySelector('input');
                const button = control.querySelector('[data-password-toggle]');
                const iconShow = button?.querySelector('[data-password-icon-show]');
                const iconHide = button?.querySelector('[data-password-icon-hide]');
                const capsWarning = control.parentElement?.querySelector('[data-caps-lock-warning]');

                if (! input || ! button) {
                    return;
                }

                button.addEventListener('click', function () {
                    const akanDitampilkan = input.type === 'password';
                    const label = akanDitampilkan ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';

                    input.type = akanDitampilkan ? 'text' : 'password';
                    button.setAttribute('aria-label', label);
                    button.setAttribute('aria-pressed', akanDitampilkan ? 'true' : 'false');
                    button.setAttribute('title', label);
                    iconShow.hidden = akanDitampilkan;
                    iconHide.hidden = ! akanDitampilkan;
                    input.focus({ preventScroll: true });
                });

                const perbaruiCapsLock = function (event) {
                    if (! capsWarning || typeof event.getModifierState !== 'function') {
                        return;
                    }

                    capsWarning.hidden = ! event.getModifierState('CapsLock');
                };

                input.addEventListener('keydown', perbaruiCapsLock);
                input.addEventListener('keyup', perbaruiCapsLock);
                input.addEventListener('blur', function () {
                    if (capsWarning) {
                        capsWarning.hidden = true;
                    }
                });
            });
        });
    </script>
@endonce
