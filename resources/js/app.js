import './bootstrap';

import Alpine from 'alpinejs';
import * as bootstrap from 'bootstrap';
import './password-toggle';
import './flash-messages';
import './bandeau-seance';
import './kiosque-etat';
import './horloge';
import './confirmation';
import './copier';
import './mot-de-passe-provisoire';

window.Alpine = Alpine;
window.bootstrap = bootstrap;

Alpine.start();
