# What Inertia v3 offers for navigation feedback

Research for [#16](https://github.com/JoepvdBroek/statsheet/issues/16), part of map [#15 Navigation feedback](https://github.com/JoepvdBroek/statsheet/issues/15). These are facts and limits only. The design decisions belong to the later tickets.

## Versions examined

| Package | Installed | Source |
| --- | --- | --- |
| `@inertiajs/core`, `@inertiajs/react`, `@inertiajs/vite` | **3.7.1** | `node_modules/@inertiajs/*/package.json` |
| `inertiajs/inertia-laravel` | 3.4.0 | `composer.lock` |
| `laravel/wayfinder` (PHP) / `@laravel/vite-plugin-wayfinder` | 0.1.21 / 0.1.10 | `composer.lock`, `node_modules` |

The docs at inertiajs.com/docs/v3 describe the **latest** v3, which was 3.11.0 on 2026-11-02 (`gh release list -R inertiajs/inertia`). Where the docs and the installed 3.7.1 differ, this file follows the source and marks the difference with **[version]**.

How the claims are sourced:

- **Docs**: inertiajs.com v3 page (URLs at the bottom).
- **Src**: installed source, cited as `core:<line>` for `node_modules/@inertiajs/core/dist/index.js` and `react:<line>` for `node_modules/@inertiajs/react/dist/index.js` (3.7.1).
- **App**: this repo.
- **Inference**: my reading of the source that hasn't been tested in a browser. Each one is labelled and should be checked with a prototype before anyone relies on it.

---

## Summary

- **Instant visits** swap to the target page component as soon as the user taps and fetch the real props in the background. They are the only built-in way to put the *destination* on screen before the server answers. They need two things the app doesn't have today:
  1. Pages that render without their page-specific props. In this app, `workouts/show` and `exercises/show` compute their layout props from page props and would throw.
  2. An explicit `component` name. The installed Wayfinder doesn't emit component names, so the `instant` shorthand does nothing here.
- **Prefetching on `hover`**, the mode the tab bar uses (`prefetch` = `['hover']`), **effectively never fires on a phone tap**. On a tap the browser fires its compatibility mouse events in one burst right before `click`, and the Link's `onClick` clears the 75 ms hover timer (Src + spec, Inference). `click` mode gets no head start on touch either. Only `mount` or a programmatic `router.prefetch()` prefetches ahead of a tap.
- **No global "navigating" hook exists.** The pieces available are the global router events (`before`/`start`/`finish`/`navigate`/…), the per-link `data-loading` attribute, and per-visit callbacks. The `start` and `finish` events also fire for prefetches, deferred-prop loads, polls and `WhenVisible` reloads, so a global pending UI has to filter them. Cached (prefetched) visits fire no global `start`/`finish` at all.
- **The progress bar** is NProgress. Its options are `delay` (default **250 ms**), `color`, `includeCSS`, `showSpinner` and `popover`, or `progress: false` to disable it. Per visit you can use `showProgress` and `async`. The bar is `aria-hidden` and announces nothing.
- **Back/forward never hits the server.** The page is restored from `history.state`: no `start`/`finish`, no progress bar, data as it was when the user left. Missing deferred props are re-requested. Only a missing or undecryptable entry, or an asset-version mismatch, triggers a real request.
- **Deferred props** render the `<Deferred fallback>` until every listed key is defined. `WhenVisible` renders its `fallback` until loaded and exposes `fetching` on later loads. Neither shows the progress bar, because both use async reloads.
- **3.7.1 has races in exactly these areas**: Back while a deferred-props response is still resolving, stale responses landing after navigation, and cached visits that can't be cancelled. They were fixed in 3.9.0 and 3.10.0 (see [Fixes after 3.7.1](#fixes-after-371-that-touch-this-topic)).

---

## 1. Instant visits

### What renders before the server responds

- You give the target component name with `<Link component="…">` or `router.visit(url, { component })`. Inertia renders that component straight away and fires the server request in the background. When the response arrives, "the full props are merged in" (Docs: instant-visits).
- The intermediate page gets **only the shared props** by default. The Laravel adapter lists them in the `sharedProps` page-object key, and the client copies those keys from the current page (Docs; Src `core:3441-3472`). Here they are `name`, `auth` and `workoutInProgressId` (App `HandleInertiaRequests::share`), plus `errors: {}`. Page-specific props are `undefined`.
- `pageProps` replaces the default props. Pass an object for full control (shared props are then **not** copied) or a callback `(currentProps, sharedProps) => props` (Docs; Src `core:3446-3447`). This is the hook for placeholder values, for example passing data you already have on the list page.
- `expose_shared_prop_keys` defaults to `true` in inertia-laravel 3.4.0 (`vendor/inertiajs/inertia-laravel/config/inertia.php:126`). The app's `config/inertia.php` doesn't override it.
- Before the swap, the component still has to be **resolved**. `@inertiajs/vite` lazy-loads pages by default (`lazy ?? true`, `node_modules/@inertiajs/vite/dist/index.js:305`; Docs: code-splitting), so the first visit to a page waits for its JS chunk before even the instant swap can happen. Prefetching fetches page *data* only, not the chunk (Src: `PrefetchedRequests` stores responses; the component is resolved in `page.set`, `core:1092`).

### How the real props arrive

- The intermediate page is set with `preserveState: false` and is **pushed** onto history; the `navigate` event fires here. The real request is then sent with `preserveState: true`, `preserveScroll: true`, `replace: true` and `viewTransition: false` (Src `core:3245-3254`, `core:3465-3471`).
- Because of `preserveState: true`, React keeps the **same component instance**. It renders once with placeholder props and again with real props, with no remount (Src `react:116-129`: the key only changes when `!preserveState`).
- Because of `replace: true`, the real response **does not fire `navigate`**. `page.set` fires `navigate` only when `!replace` (`core:1141-1143`). A pending UI that ends on `navigate` would therefore end at the placeholder swap. `success` and `finish` fire when the real response lands.
- A prefetched response is used if one is cached, even for an instant visit. `sendRequest` checks the cache after the swap (`core:3228-3238`).

### Interaction with layout props (title/tab here)

- The React adapter calls a page's `layout` resolver with the **current page props**: `layoutValue(props)` (`react:157-168`). On an intermediate page those are shared props or `pageProps` only.
- In this app:
  - **Static** layout objects (`home`, `workouts/index`, `statistics/hub`, …) render the right header title and active tab immediately, so the tab bar and header switch on tap.
  - **Prop-derived** layouts would throw on the placeholder: `ShowWorkout.layout = ({ workout }) => isInProgress(workout) …` (`resources/js/pages/workouts/show.tsx:720`, and `isInProgress` reads `workout.status`) and `ShowExercise.layout = ({ exercise }) => ({ title: exercise.name, … })` (`resources/js/pages/exercises/show.tsx:230`). Instant visits to these need `pageProps` that supply `workout`/`exercise`, or a layout resolver that tolerates `undefined`.
- **Dynamic** layout props (`setLayoutProps`) are reset by `resetLayoutProps()` only when `!preserveState` (`react:120-122`). So they're reset at the placeholder swap but **not** when the real response lands. Whatever the placeholder render set stays until the page sets it again. Only `auth/two-factor-challenge` uses `setLayoutProps` today.
- The docs and AGENTS.md mention a `useLayoutProps` hook, but `@inertiajs/react` 3.7.1 doesn't export it. Its exports are `setLayoutProps` and `resetLayoutProps` (`react:2129-2151`). Layout components receive the props directly.

### Wayfinder shorthand

- `<Link href={show(1)} instant>` reads the component from the Wayfinder route definition. This needs `generate.inertia.component` in `config/wayfinder.php` (Docs).
- **The installed Wayfinder (PHP 0.1.21) has no such option.** There's no `config/wayfinder.php`, `vendor/laravel/wayfinder/src` doesn't mention `component`, and the generated `resources/js/routes/workouts/index.ts` has no `component` field. With no component, the `instant` prop resolves to `null` and the visit is a normal one (`react:1533-1541`). Today an explicit `component="workouts/show"` is required, or a Wayfinder upgrade, which is a dependency change that needs approval.
- `<Form instant>` and `<Form component>` exist too (Docs; Src `react:933-964`).

### Failure modes (Src, partly Inference)

| Case | What happens |
| --- | --- |
| Server **redirects** (GET → 302) | The XHR follows the redirect. The final Inertia response replaces the history entry and its URL (`replace: true`, `pageUrl`, `core:2621`, `2657-2665`). |
| Response is a **different component** | `page.set` resolves and swaps it in. React remounts because the element type differs. State is "preserved" in name only. Scroll is preserved (`preserveScroll: true`) rather than reset. |
| **Validation errors** on an instant `<Form>` | The server redirects back, so the user sees the target placeholder and then bounces back to the form page with errors. *Inference*: the placeholder was pushed with the action URL and is then replaced by the form page's URL, so history may hold the form page **twice**. |
| **Network error / offline** | The `networkError` event fires and the promise rejects (`core:2939-2951`). The placeholder page **stays on screen** with its URL already pushed, and nothing retries. *Inference*: that history entry keeps the propless placeholder state. Back then Forward would restore the placeholder from history without a request. |
| **Non-Inertia response** (HTML 500 in dev) | The error modal opens over the placeholder (`core:2549-2558`). Cancel `httpException` to suppress it. |
| **Inertia error page** (`Inertia::handleExceptionsUsing`, status ≥ 400) | `httpException` fires. Unless cancelled, the error page component is rendered (`core:2491-2505`; Docs: error-handling). |
| **Asset version changed** (409) | `location` event fires, then a full page reload (`core:2584-2604`). |
| User taps **Back** during the wait | `popstate` calls `router.cancelAll({ prefetch: false })` and restores the previous entry (`core:1613-1619`). |
| Array or multiple components | Logged and falls back to a normal visit (`core:3239-3244`, `1018-1025`). |

---

## 2. Prefetching

### Modes (Docs; Src `react:1616-1745`)

| `prefetch` value | Trigger | Default `cacheFor` |
| --- | --- | --- |
| `true` / `'hover'` | `mouseenter`, then `prefetch.hoverDelay` (**75 ms**). Cleared on `mouseleave` or `click`. | `prefetch.cacheFor` = **30 s** (`core:41-44`) |
| `'click'` | `mousedown` / `keydown` prefetches, `mouseup` / `keyup` visits | **0**, single use (`react:1635-1636`) |
| `'mount'` | `setTimeout` after the Link mounts | 30 s |
| array, e.g. `['mount', 'hover']` | combined | 30 s |

- **Touch behaviour (Src + spec, Inference)**: the Link only binds mouse and keyboard handlers, never `touchstart`/`pointerdown`. On a tap, browsers fire compatibility mouse events **after `touchend`, back to back**: mousemove, mousedown, mouseup, click (W3C Touch Events, "Interaction with Mouse Events and click").
  - Hover mode: the timer starts on the emulated enter and `onClick` clears it a moment later (`react:1672-1684`). **No prefetch happens on a phone**, so the tab bar's `prefetch` only helps on desktop.
  - Click mode: `mousedown` and `mouseup` arrive in the same burst, so the visit starts when it would have anyway.
  - `mount`, or a custom `router.prefetch()` (for example on `pointerdown`/`touchstart`), are the only ways to get ahead of a tap.
  - Confirm on a device: look in remote DevTools for requests with the `Purpose: prefetch` header (`core:3559-3561`).
- **`mount` in a persistent layout**: the effect depends only on `prefetchModes` (`react:1658-1662`). In the persistent `TabBar` it fires **once per mount**, not on every navigation, and it doesn't re-fire when `href` changes (the Workout tab's target changes when a Workout starts). The entries expire after `cacheFor` and nothing re-fetches them.
- A prefetch is skipped when the target is the current URL, and it must be `GET` (`core:3274-3290`).
- A prefetch is a full Inertia page request with every non-deferred prop and the shared props. Deferred props are not included. They load after the page is shown.

### `cacheFor` and stale-while-revalidate

- `cacheFor` accepts ms, `'10s'` or `'1m'`, or `[fresh, expires]` (Docs; `core:469-485`).
- **[version]** The docs say a visit during the stale window "is served … and a request is made in the background to refresh". In 3.7.1:
  - A **navigation** that finds a cached entry uses it without revalidating (`core:3228-3233`, `511-527`).
  - The stale timestamp only decides whether a **new prefetch trigger** re-fetches (`core:383-386`).
  - When such a re-fetch arrives while the user is already on that URL, it is applied to the page (`Response.handlePrefetch`, `core:2470-2474`).
- Cache keys are the full visit params minus callbacks and flags (`core:553-584`). So `only`, `data`, `headers` and so on make separate entries.

### Invalidation

- Calls: `router.flush(url, options)`, `router.flushAll()`, `router.flushByCacheTags(tags)`, `usePrefetch().flush()` (current page only) (Docs; `core:3262-3270`).
- `cacheTags` on Link or `router.prefetch()`. `invalidateCacheTags` on `<Form>`, `useForm` submits and `router.post/put/patch/delete`, flushed on success (Docs; `core:2517`).
- **Automatic flushing**: after a successful non-prefetched response, Inertia flushes the cache entry for the URL it **landed on** (`core:2518-2520`). It does **not** flush other pages after a mutation, and it doesn't flush on navigation unless you add `router.on('navigate', () => router.flushAll())` (Docs). Example: after ticking Sets, a cached prefetch of Workout history or Stats can be up to `cacheFor` old.
- **[version]** 3.9.0 flushes the prefetch cache when history is cleared (PR #3307).

### Cost on mobile data

- Each prefetch costs one server round trip plus the full page JSON. `mount` on the three tab links means up to three page payloads per app load, re-fetched only when the Links remount.
- Prefetch requests carry `Purpose: prefetch`. inertia-laravel reads it via `$request->prefetch()` (`vendor/inertiajs/inertia-laravel/src/Middleware.php:177,225`), so the server could treat them differently.
- The cache lives only in memory: one JS module per tab, with timers (`core:373-620`).

---

## 3. Global and per-link pending state

### Router events (Docs: events; Src `core:63-108`)

`before` (cancelable), `start`, `progress` (uploads only), `success`, `error` (validation), `httpException` (cancelable), `networkError` (cancelable), `finish`, `navigate`, `location` (cancelable, 3.6+), `prefetching`, `prefetched`, `flash`, `beforeUpdate`, `clientVisit`. Register with `router.on(name, cb)`, which returns an unsubscribe function, or `document.addEventListener('inertia:' + name)`. `router.once` also exists.

- `finish`'s `event.detail.visit` carries `completed`, `interrupted` or `cancelled` (Docs: progress-indicators). A second navigation interrupts the first, since the sync stream allows one request at a time (`core:3056-3059`, `3215-3217`).
- **[version]** `navigate.detail.type` (`initial` | `visit` | `history`) is **3.9.0+**. In 3.7.1 the detail is `{ page, cached, visitId }` (`core:81-83`).
- **What `start`/`finish` cover** (Src `core:2907-2973`): every request through `Request.send`, which includes prefetches (`visit.prefetch`), deferred-prop loads (`visit.deferredProps`, `async`), `router.reload`, polls and `WhenVisible`. Filter on `visit.prefetch`, `visit.async` or `visit.deferredProps`. *Inference*: `Response.process` sets `prefetch = false` on the same params object before `finish` fires (`core:2479-2481`), so a prefetch's `finish` may no longer say `prefetch: true`. Pairing `start` and `finish` by `visit.id` is safer.
- **Cached visits fire no global `start`/`finish`.** `prefetchedRequests.use()` goes straight to `response.handle()` (`core:511-527`). With the click handled by a cached entry, the next global event is `navigate` (`detail.cached === true`).
- **History visits** fire only `navigate` (see §5).
- There is no React hook for "a navigation is pending". `usePrefetch()` reports whether the **current** page came from the prefetch cache (`react:1994-2027`). It doesn't track links.

### Per-link and per-visit

- **`data-loading`** is set on a `<Link>` while its own visit is in flight (Docs: links; `react:1528`, `1590-1598`, `1742`). Tailwind can target it with `data-loading:`. The persistent tab bar keeps its Links mounted, so the attribute stays visible for the whole wait. For Links inside a page, the page unmounts when the new one swaps in.
- *Inference*: when a visit is served from a prefetch, the recorded `onStart` and `onFinish` are replayed back to back when the response is handled (`core:2330-2348`, `2486`). `data-loading` flickers on and off in the same tick, and the per-visit `onFinish` runs **before** the page swap.
- Per-visit callbacks (`onBefore`, `onStart`, `onFinish`, `onCancel`, `onSuccess`, `onError`, `onHttpException`, `onNetworkError`, …) are on `router.visit` and Link props (Docs: manual-visits). `onCancelToken` and `router.cancelAll({ sync, async, prefetch })` allow user-initiated cancellation.
- Forms: `<Form>` and `useForm` expose `processing` (Docs: forms; out of scope for #15 except for redirecting actions like Start Workout).

---

## 4. Progress indicator

- Built in: an NProgress wrapper configured through `createInertiaApp({ progress })` (Docs; Src `core:5100-5145`).

  | Option | Default | Notes |
  | --- | --- | --- |
  | `delay` | **250 ms** | The bar only starts if the request is still running after this |
  | `color` | `#29d` | The app sets `#3ddc91` (`resources/js/app.tsx`) |
  | `includeCSS` | `true` | Injects inline styles. A CSP needs a `nonce` |
  | `showSpinner` | `false` | Top-right spinner |
  | `popover` | auto | Renders in the top layer via the Popover API when supported, so it sits above dialogs (`core:4764-4777`) |

- `progress: false` disables it. The `progress` export (`start`, `set`, `finish`, `reset`, `remove`, `hide`, `reveal`, `isStarted`, `getStatus`) only works while the built-in bar is enabled (Docs).
- Per visit, `showProgress: false` or `async: true` hides it. `async` defaults to no progress unless `showProgress: true` (Docs; `core:3199-3202`). Prefetches, deferred props and `router.reload` are async, so they don't show the bar (`core:3111-3128`, `3274-3285`).
- When a click finds an **in-flight** prefetch, the bar is force-revealed (`progress.reveal(prefetched.inFlight)`, `core:3229-3233`). When it finds a **completed** cached entry, no bar appears.
- The bar is a 2 px `position: fixed; top: 0` strip with `aria-hidden="true"` (`core:4770-4772`, CSS `core:4935+`). It has no ARIA live region and no `prefers-reduced-motion` handling. The app uses `viewport-fit=cover` (`resources/views/app.blade.php:5`). Whether the strip sits under the status bar in standalone mode needs checking on a device.
- For a custom indicator, the docs give the `start`/`finish` recipe, including the delay timeout and the `completed`/`interrupted`/`cancelled` handling (Docs: progress-indicators).

---

## 5. Back/forward

- A `popstate` with a valid `state.page` restores the page from history: `history.decrypt(state.page)` then `page.setQuietly(data, { preserveState: false })`. Scroll regions are restored and `navigate` fires. **No request is sent**, no `start`/`finish` fires and no progress bar shows (`core:1601-1637`). The user sees the props **as they were when the page was left**, which matters for pages whose data changed in the meantime, such as a Workout in progress.
- Before restoring, it calls `router.cancelAll({ prefetch: false })`.
- **Deferred props** that are missing from the restored state are re-requested (`loadDeferredProps`, `core:1622-1632`). After a completed deferred load the props are normally stored in history already, because the partial reload replaced the entry.
- A **server request** happens only when:
  - the entry is missing or invalid, or decryption fails (history encryption is off here: `INERTIA_ENCRYPT_HISTORY` defaults to `false`), or
  - the asset version differs (`onMissingHistoryItem` then `router.visit(location.href, { preserveState, preserveScroll, replace })`, `core:1582-1585`, `3080-3084`).
- A full-page **back_forward** load (for example after the OS killed the PWA) also restores from `history.state` without a request (`InitialVisit.handleBackForward`, `core:1692-1708`).
- `popstate` can't be cancelled, so a back navigation can't be blocked (Docs: events).
- `navigate.detail.type === 'history'` is **[version] 3.9.0+**. In 3.7.1 a history navigation can only be told apart because it has no preceding `start` and no `visitId`.

---

## 6. Deferred props and `WhenVisible`

- **`<Deferred data fallback rescue>`** (`react:357-406`):
  - Renders `fallback` until **every** key in `data` is `!== undefined` on the page props. `fallback` is required: the component throws without it.
  - Once loaded, it renders `children`, a node or `({ reloading }) => …`. `reloading` is true during a partial reload of those keys.
  - If a key is in `rescuedProps` (server `defer(..., rescue: true)`), it renders `rescue({ reloading })`.
  - Because it only checks for `undefined`, `<Deferred>` also shows its fallback for **non-deferred** props that are missing on an instant-visit placeholder (Inference from the code).
- **Loading**: deferred groups load after the page is shown, one async `router.reload({ only, deferredProps: true, preserveErrors: true })` per group (`core:1137-1139`, `3625-3631`). They show no progress bar. They do fire `start`/`finish` with `async: true` and `deferredProps: true`.
- **App today**: `WorkoutController::show` and `RoutineController` defer `exercises`. `exercise-picker.tsx` checks `exercises === undefined` itself and renders `<Skeleton>` rows instead of using `<Deferred>`.
- **`<WhenVisible data fallback buffer as always params>`** (`react:2029-2124`; Docs: load-when-visible):
  - Renders `fallback` inside a wrapper (default `div`) until the props are loaded.
  - An IntersectionObserver with `rootMargin = buffer` px triggers `router.reload({ only: data, preserveErrors: true })`.
  - With `always`, it re-fetches on every intersection and exposes `({ fetching })` to children. `fallback` is only for the first load.
- The Inertia v3 guidance in AGENTS.md asks for "an empty state with a pulsing or animated skeleton" for deferred props.

---

## Other relevant facts

- **View transitions**: `viewTransition` (boolean or callback) on Link and `router.visit` wraps the DOM swap in `document.startViewTransition`, with a cross-fade by default. It is skipped when unsupported or when the document is hidden (Docs: view-transitions; `core:1194-1220`). The swap happens only when the response arrives, so it animates the change but doesn't shorten the wait. Instant visits apply it only to the placeholder swap.
- **No request timeout**: the built-in XHR client sets no `timeout` and only rejects on `error` or `abort` (`core:2029-2108`). A stalled request on a weak signal stays pending until the browser gives up. A timeout would need a cancel token or `router.cancelAll()` driven by our own timer, or an HTTP interceptor.
- **Optimistic updates** exist (`router.optimistic`, `optimistic` option), but #15 puts in-place actions out of scope.

## Fixes after 3.7.1 that touch this topic

| Release | PR | Effect |
| --- | --- | --- |
| 3.8.0 | #3264 | Compare asset versions before restoring history on a back/forward reload |
| 3.8.0 | #3271 | Clean up in-flight cancelled prefetches |
| 3.9.0 | #3274 | Back while a deferred, poll or partial response is still resolving a lazy page component could leave the page you just left on screen at the previous URL. This is the app's exact setup: deferred `exercises` plus lazy chunks |
| 3.9.0 | #3291 | `navigate` event `type` |
| 3.9.0 | #3307 | Flush the prefetch cache when history is cleared |
| 3.10.0 | #3344 | Stale async responses (deferred, poll, partial) no longer revert a navigation. Back/Forward cancel them first |
| 3.10.0 | #3360 | Visits served from the prefetch cache become cancellable and interruptible. Before this, Back or a newer visit couldn't stop them |
| 3.10.0 | #3356 | Keep shared once props on instant visits |
| 3.10.0 | — | `QUERY` method prefetching |

Source: `gh release view v3.x.y -R inertiajs/inertia` and the PR bodies.

## Trade-offs and limits for a phone PWA (facts, not a recommendation)

- Hover prefetch doesn't help on touch. Prefetching on a phone means `mount` (more data, fires once per mount) or a custom `pointerdown` trigger, which gets roughly the tap duration as a head start.
- Instant visits give the most immediate feedback, because the destination's header and active tab show at once. They cost:
  - per-page placeholder handling, since props are `undefined`,
  - an explicit `component` name or a Wayfinder upgrade,
  - care with prop-derived layouts,
  - a placeholder left on screen with no retry when the network fails.
- Back/forward is instant but can show stale data. Nothing refreshes a restored page unless we add it (for example, a `router.reload` on history `navigate`).
- The progress bar is the only built-in visual feedback. It has a 250 ms delay, is 2 px tall and is invisible to assistive tech.
- Several of the edge cases above are fixed only in ≥ 3.10.0. Building on 3.7.1 inherits them.

## Sources

- Instant visits: https://inertiajs.com/docs/v3/the-basics/instant-visits
- Prefetching: https://inertiajs.com/docs/v3/data-props/prefetching
- Events: https://inertiajs.com/docs/v3/advanced/events
- Progress indicators: https://inertiajs.com/docs/v3/advanced/progress-indicators
- Links (`data-loading`, view transitions): https://inertiajs.com/docs/v3/the-basics/links
- Manual visits (options, cancellation, callbacks): https://inertiajs.com/docs/v3/the-basics/manual-visits
- Layouts (layout props): https://inertiajs.com/docs/v3/the-basics/layouts
- Deferred props: https://inertiajs.com/docs/v3/data-props/deferred-props
- Load when visible: https://inertiajs.com/docs/v3/data-props/load-when-visible
- View transitions: https://inertiajs.com/docs/v3/the-basics/view-transitions
- Code splitting: https://inertiajs.com/docs/v3/advanced/code-splitting
- Error handling: https://inertiajs.com/docs/v3/advanced/error-handling
- History encryption: https://inertiajs.com/docs/v3/security/history-encryption
- The protocol (`sharedProps`): https://inertiajs.com/docs/v3/core-concepts/the-protocol
- W3C Touch Events, "Interaction with Mouse Events and click": https://w3c.github.io/touch-events/#mouse-events
- Releases: https://github.com/inertiajs/inertia/releases (v3.8.0 to v3.11.0), PRs #3274, #3344, #3360
- Installed source: `node_modules/@inertiajs/core/dist/index.js`, `node_modules/@inertiajs/react/dist/index.js`, `node_modules/@inertiajs/vite/dist/index.js` (3.7.1), `vendor/inertiajs/inertia-laravel` (3.4.0)
