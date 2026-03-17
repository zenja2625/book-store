import '../scss/app.scss'
import 'bootstrap';

addEventListener('page:loaded', () => {
    if (document.querySelector('[data-embla-root]'))
        import('./carousel').then(module => module.initEmbla());
});