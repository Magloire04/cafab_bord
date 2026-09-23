import './bootstrap';

import Alpine from 'alpinejs';
import * as bootstrap from 'bootstrap';
import AOS from 'aos';
import './password-toggle';
import './flash-messages';

window.Alpine = Alpine;
window.bootstrap = bootstrap;

Alpine.start();

AOS.init({
    duration: 800,
    once: true,
});
