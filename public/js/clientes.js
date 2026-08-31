document.addEventListener('DOMContentLoaded', () => {
    const cnpjInputs = document.querySelectorAll(
        'input[data-mask="cnpj"]'
    );

    const onlyDigits = (value) => {
        return String(value ?? '')
            .replace(/\D/g, '')
            .slice(0, 14);
    };

    const formatCnpj = (value) => {
        const digits = onlyDigits(value);

        if (digits.length <= 2) {
            return digits;
        }

        if (digits.length <= 5) {
            return digits.replace(
                /^(\d{2})(\d+)/,
                '$1.$2'
            );
        }

        if (digits.length <= 8) {
            return digits.replace(
                /^(\d{2})(\d{3})(\d+)/,
                '$1.$2.$3'
            );
        }

        if (digits.length <= 12) {
            return digits.replace(
                /^(\d{2})(\d{3})(\d{3})(\d+)/,
                '$1.$2.$3/$4'
            );
        }

        return digits.replace(
            /^(\d{2})(\d{3})(\d{3})(\d{4})(\d{0,2})$/,
            '$1.$2.$3/$4-$5'
        );
    };

    cnpjInputs.forEach((input) => {
        input.value = formatCnpj(
            input.value
        );

        input.addEventListener('input', () => {
            const start =
                input.selectionStart
                ?? input.value.length;

            const digitsBeforeCursor = onlyDigits(
                input.value.slice(
                    0,
                    start
                )
            ).length;

            input.value = formatCnpj(
                input.value
            );

            /*
             * Mantém o cursor próximo ao ponto lógico digitado,
             * mesmo quando pontuação, barra ou hífen são inseridos.
             */
            if (
                typeof input.setSelectionRange === 'function'
                && document.activeElement === input
            ) {
                let position = 0;
                let seenDigits = 0;

                while (
                    position < input.value.length
                    && seenDigits < digitsBeforeCursor
                ) {
                    if (/\d/.test(input.value[position])) {
                        seenDigits++;
                    }

                    position++;
                }

                input.setSelectionRange(
                    position,
                    position
                );
            }
        });

        input.addEventListener('blur', () => {
            input.value = formatCnpj(
                input.value
            );
        });
    });

    /*
     * Antes do POST removemos a máscara.
     *
     * Isso mantém compatibilidade com o backend e com a constraint
     * atual, que armazenam o CNPJ somente com 14 números.
     * O backend normaliza novamente como defesa em profundidade.
     */
    document.querySelectorAll(
        'form[data-client-form]'
    ).forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll(
                'input[data-mask="cnpj"]'
            ).forEach((input) => {
                input.value = onlyDigits(
                    input.value
                );
            });
        });
    });
});
