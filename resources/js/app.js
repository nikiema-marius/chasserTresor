/*
 * Livewire + Alpine sont chargés ici (bundle unique, avec nonce CSP) plutôt
 * qu'injectés automatiquement : cela permet d'enregistrer nos composants Alpine
 * avant le démarrage.
 */
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import qrScanner from './scanner';

Alpine.data('qrScanner', qrScanner);

Livewire.start();
