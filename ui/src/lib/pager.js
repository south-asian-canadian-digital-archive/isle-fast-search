// template_preprocess_pager()'s window: `quantity` page links around the
// current page, shifted to stay inside 1..last.
export function pagerWindow(current, total, quantity = 5) {
  const middle = Math.ceil(quantity / 2);
  const now = current + 1;
  let first = now - middle + 1;
  let last = now + quantity - middle;
  if (last > total) {
    first += total - last;
    last = total;
  }
  if (first <= 0) {
    last += 1 - first;
    first = 1;
  }
  const pages = [];
  // Drupal's loop also stops at the last real page; without this a short
  // result set still shows `quantity` links.
  last = Math.min(last, total);
  for (let i = first; i <= last; i++) pages.push(i);
  return { pages, before: first > 1, after: last < total };
}
