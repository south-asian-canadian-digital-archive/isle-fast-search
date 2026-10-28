import { mount } from 'svelte';
import App from './App.svelte';
import { configure } from './lib/search.svelte.js';

const target = document.getElementById('sacda-fast-search');
if (target) {
  const settings = window.drupalSettings?.sacdaFastSearch ?? {};
  configure(settings);
  mount(App, { target, props: { endpoint: settings.endpoint ?? '/api/search' } });
}
