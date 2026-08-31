document.addEventListener('DOMContentLoaded', () => {
    const productSelect = document.querySelector(
        '[data-product-select]'
    );

    const planSelect = document.querySelector(
        '[data-plan-select]'
    );

    const planTemplate = document.querySelector(
        '#assinatura-planos-template'
    );

    if (
        !(productSelect instanceof HTMLSelectElement)
        || !(planSelect instanceof HTMLSelectElement)
        || !(planTemplate instanceof HTMLTemplateElement)
    ) {
        return;
    }

    const buildPlanOptions = (
        productId,
        selectedPlanId = ''
    ) => {
        /*
         * Sempre recria o select a partir do catálogo entregue
         * pelo backend na renderização.
         *
         * O catálogo no navegador é apenas UX.
         * A relação produto/plano será validada novamente
         * pelo backend no POST.
         */
        planSelect.replaceChildren();

        const placeholder = document.createElement(
            'option'
        );

        placeholder.value = '';

        if (productId === '') {
            placeholder.textContent =
                'Selecione primeiro o produto';

            planSelect.append(
                placeholder
            );

            planSelect.disabled = true;

            return;
        }

        placeholder.textContent =
            'Selecione o plano';

        planSelect.append(
            placeholder
        );

        const availableOptions = Array.from(
            planTemplate.content.querySelectorAll(
                'option[data-produto-id]'
            )
        ).filter((option) => {
            return option.dataset.produtoId
                === productId;
        });

        availableOptions.forEach((sourceOption) => {
            const option = document.createElement(
                'option'
            );

            option.value =
                sourceOption.value;

            option.textContent =
                sourceOption.textContent?.trim()
                ?? '';

            if (
                selectedPlanId !== ''
                && option.value === selectedPlanId
            ) {
                option.selected = true;
            }

            planSelect.append(
                option
            );
        });

        planSelect.disabled = false;

        /*
         * Se o produto não possuir plano ativo, deixamos o campo
         * habilitado somente para mostrar a mensagem de forma clara.
         */
        if (availableOptions.length === 0) {
            placeholder.textContent =
                'Nenhum plano disponível para este produto';
        }
    };

    /*
     * Na primeira carga preservamos eventual seleção retornada
     * pelo backend após erro de validação.
     */
    const initialProductId =
        productSelect.value;

    const initialPlanId =
        planSelect.value;

    buildPlanOptions(
        initialProductId,
        initialPlanId
    );

    productSelect.addEventListener(
        'change',
        () => {
            /*
             * Ao trocar de produto o plano anterior deixa de ser
             * elegível e deve ser descartado.
             */
            buildPlanOptions(
                productSelect.value
            );
        }
    );
});
