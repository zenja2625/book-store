
import EmblaCarousel from 'embla-carousel'

const emblaNode = document.querySelector('[data-embla-root]')
const viewportNode = emblaNode.querySelector('.embla-viewport')

const options = {
    align: 'start',
    slidesToScroll: 1,
    containScroll: 'trimSnaps',
    skipSnaps: true,
    dragFree: false,
}

const emblaApi = EmblaCarousel(viewportNode, options)

const prevButtonNode = emblaNode.querySelector('[data-embla-btn="prev"]')
const nextButtonNode = emblaNode.querySelector('[data-embla-btn="next"]')

syncButtons()

emblaApi
    .on('select', syncButtons)
    .on('reInit', syncButtons)

prevButtonNode.addEventListener('click', () => emblaApi.scrollPrev(), false)
nextButtonNode.addEventListener('click', () => emblaApi.scrollNext(), false)

function syncButtons() {
    const prevDisabled = !emblaApi.canScrollPrev()
    const nextDisabled = !emblaApi.canScrollNext()

    prevButtonNode.classList.toggle('disabled', prevDisabled)
    nextButtonNode.classList.toggle('disabled', nextDisabled)
}