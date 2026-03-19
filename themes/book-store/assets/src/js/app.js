import '../scss/app.scss'
import { Toast } from 'bootstrap';


const initFlashToasts = () => {
    const toastElements = document.querySelectorAll('.js-toast:not(.show)');

    toastElements.forEach(el => {
        const instance = Toast.getOrCreateInstance(el);
        instance.show();

        el.addEventListener('hidden.bs.toast', () => {
            el.remove();
        });
    });
};

document.addEventListener('ajax:update', initFlashToasts);

addEventListener('page:loaded', () => {
    initFlashToasts();

    if (document.querySelector('[data-embla-root]'))
        import('./carousel').then(module => module.initEmbla());
});

