// Search state shared by every component, kept in the URL in exactly the form
// /search uses, so links are interchangeable between the two pages:
//   a[n][c|f|i|v]  advanced_search terms     f[n]=alias:value  facets
//   sort_by/sort_order  exposed sort          items_per_page, display, page

export const FIELDS = [
  ['all', 'Keyword'],
  ['title_aggregated_fulltext', 'Title'],
  ['subject_aggregated_fulltext', 'Subjects'],
  ['field_resource_type', 'Resource Type'],
  ['abstract_description_fulltext', 'Description'],
  ['linked_agent_name_fulltext', 'Names'],
];

export const ORDERS = [
  ['search_api_relevance_asc', 'Relevance ↓'],
  ['search_api_relevance_desc', 'Relevance ↑'],
  ['title_asc', 'Title ↓'],
  ['title_desc', 'Title ↑'],
  ['edtf_year_asc', 'Year ↓'],
  ['edtf_year_desc', 'Year ↑'],
];

export const PER_PAGE = [15, 60, 120];

const blankTerm = () => ({ c: 'AND', f: 'all', i: 'IS', v: '' });

class SearchState {
  terms = $state([blankTerm()]);
  facets = $state([]);
  sortBy = $state('');
  sortOrder = $state('');
  perPage = $state(15);
  display = $state('grid');
  page = $state(0);

  data = $state(null);
  loading = $state(false);
  error = $state('');

  #endpoint = '/api/search';
  #responses = new Map();
  #controller = null;

  get order() {
    return this.sortBy ? `${this.sortBy}_${this.sortOrder.toLowerCase() || 'desc'}` : 'search_api_relevance_desc';
  }

  init(endpoint) {
    this.#endpoint = endpoint;
    this.readUrl();
    this.load(false);
    window.addEventListener('popstate', () => {
      this.readUrl();
      this.load(false);
    });
  }

  readUrl() {
    const params = new URLSearchParams(location.search);
    const terms = [];
    for (const [key, value] of params) {
      const m = key.match(/^a\[(\d+)\]\[([cfiv])\]$/);
      if (m) (terms[m[1]] ??= blankTerm())[m[2]] = value;
    }
    this.terms = terms.filter(Boolean).length ? terms.filter(Boolean) : [blankTerm()];
    this.facets = [...params.entries()].filter(([k]) => /^f\[\d*\]$/.test(k)).map(([, v]) => v);
    this.sortBy = params.get('sort_by') ?? '';
    this.sortOrder = params.get('sort_order') ?? '';
    this.perPage = PER_PAGE.includes(Number(params.get('items_per_page'))) ? Number(params.get('items_per_page')) : 15;
    this.display = params.get('display') === 'list' ? 'list' : 'grid';
    this.page = Math.max(0, Number(params.get('page')) || 0);
  }

  /** Query string for the current state, with optional overrides. */
  query(overrides = {}) {
    const s = { terms: this.terms, facets: this.facets, sortBy: this.sortBy, sortOrder: this.sortOrder, perPage: this.perPage, display: this.display, page: this.page, ...overrides };
    const params = new URLSearchParams();
    s.terms.filter((t) => t.v.trim()).forEach((t, n) => {
      if (n > 0) params.set(`a[${n}][c]`, t.c);
      params.set(`a[${n}][f]`, t.f);
      params.set(`a[${n}][i]`, t.i);
      params.set(`a[${n}][v]`, t.v.trim());
    });
    s.facets.forEach((v, n) => params.set(`f[${n}]`, v));
    if (s.sortBy) {
      params.set('sort_by', s.sortBy);
      params.set('sort_order', s.sortOrder || 'DESC');
    }
    if (s.perPage !== 15) params.set('items_per_page', String(s.perPage));
    if (s.display !== 'grid') params.set('display', s.display);
    if (s.page) params.set('page', String(s.page));
    return params.toString();
  }

  href(overrides = {}) {
    const qs = this.query(overrides);
    return qs ? `?${qs}` : location.pathname;
  }

  /** Apply overrides, push the URL and fetch. */
  go(overrides = {}) {
    Object.assign(this, overrides);
    this.load(true);
  }

  async load(push) {
    const qs = this.query({ display: 'grid' });
    if (push) history.pushState(null, '', this.href());
    if (this.#responses.has(qs)) {
      this.data = this.#responses.get(qs);
      return;
    }
    this.#controller?.abort();
    this.#controller = new AbortController();
    this.loading = true;
    this.error = '';
    try {
      const res = await fetch(`${this.#endpoint}${qs ? `?${qs}` : ''}`, { signal: this.#controller.signal });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error ?? `Search failed (${res.status}).`);
      this.#responses.set(qs, json);
      this.data = json;
    } catch (e) {
      if (e.name !== 'AbortError') this.error = e.message;
    } finally {
      this.loading = false;
    }
  }

  toggleFacet(alias, value) {
    const key = `${alias}:${value}`;
    this.go({ facets: this.facets.includes(key) ? this.facets.filter((f) => f !== key) : [...this.facets, key], page: 0 });
  }

  setYear(alias, min, max) {
    const rest = this.facets.filter((f) => !f.startsWith(`${alias}:`));
    const value = min === '' && max === '' ? [] : [`${alias}:(min:${min},max:${max})`];
    this.go({ facets: [...rest, ...value], page: 0 });
  }

  setOrder(order) {
    const m = order.match(/^(.+)_(asc|desc)$/);
    this.go({ sortBy: m[1], sortOrder: m[2].toUpperCase(), page: 0 });
  }

  addTerm(after) {
    this.terms.splice(after + 1, 0, blankTerm());
  }

  removeTerm(n) {
    this.terms.splice(n, 1);
    if (!this.terms.length) this.terms.push(blankTerm());
  }

  reset() {
    this.go({ terms: [blankTerm()], facets: [], page: 0 });
  }
}

export const search = new SearchState();
