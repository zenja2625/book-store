import EmblaCarousel from 'embla-carousel'

export function initEmbla() {
    const emblaNodes = document.querySelectorAll('[data-embla-root]');

    emblaNodes.forEach(emblaNode => {
        const viewportNode = emblaNode.querySelector('.embla-viewport');
        if (!viewportNode) return;

        const options = {
            align: 'start',
            slidesToScroll: 1,
            containScroll: 'trimSnaps',
            skipSnaps: true,
            dragFree: false,
        };

        const emblaApi = EmblaCarousel(viewportNode, options);

        const prevButtonNode = emblaNode.querySelector('[data-embla-btn="prev"]');
        const nextButtonNode = emblaNode.querySelector('[data-embla-btn="next"]');

        const syncButtons = () => {
            const prevDisabled = !emblaApi.canScrollPrev();
            const nextDisabled = !emblaApi.canScrollNext();
            if (prevButtonNode) prevButtonNode.classList.toggle('disabled', prevDisabled);
            if (nextButtonNode) nextButtonNode.classList.toggle('disabled', nextDisabled);
        };

        emblaApi
            .on('select', syncButtons)
            .on('reInit', syncButtons);

        prevButtonNode?.addEventListener('click', emblaApi.scrollPrev, false);
        nextButtonNode?.addEventListener('click', emblaApi.scrollNext, false);

        syncButtons();

        addEventListener('page:unload', () => {
            prevButtonNode?.removeEventListener('click', emblaApi.scrollPrev);
            nextButtonNode?.removeEventListener('click', emblaApi.scrollNext);
            emblaApi.destroy();
        }, { once: true });
    });
}