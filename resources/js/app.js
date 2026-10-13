import './bootstrap';
import * as bootstrap from 'bootstrap';
import * as Turbo from '@hotwired/turbo';

window.bootstrap = bootstrap;
window.Turbo = Turbo;

// Alpine.js for Livewire (kung ginagamit)
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

import Chart from 'chart.js/auto';
window.Chart = Chart;
