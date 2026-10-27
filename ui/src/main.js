import { mount } from 'svelte';
import App from './App.svelte';

const target = document.getElementById('sacda-fast-search');
if (target) {
  mount(App, { target, props: { endpoint: window.drupalSettings?.sacdaFastSearch?.endpoint ?? '/api/search' } });
}
