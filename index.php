<?php
$assetVersion = '2026-05-23-3';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Inventory</title>
  <meta name="description" content="Personal warehouse-style inventory tracker." />
  <meta name="theme-color" content="#1f4d43" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-title" content="Inventory" />
  <link rel="manifest" href="manifest.json" />
  <link rel="apple-touch-icon" href="icons/icon-180.png" />
  <link rel="icon" href="favicon.ico" sizes="any" />
  <link rel="icon" href="icons/favicon-32.png" type="image/png" sizes="32x32" />
  <link rel="icon" href="icons/favicon-16.png" type="image/png" sizes="16x16" />
  <link rel="stylesheet" href="styles.css?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>" />
</head>
<body>
  <main class="app-shell">
    <header class="app-header">
      <div>
        <p class="eyebrow">Stores Lookup</p>
        <h1>Inventory</h1>
      </div>
      <div class="stats" aria-live="polite">
        <span><strong id="item-count">0</strong> lines</span>
        <span><strong id="unit-count">0</strong> units</span>
        <span><strong id="location-count">0</strong> bins</span>
      </div>
    </header>

    <section class="entry-panel" aria-labelledby="entry-title">
      <div class="section-head">
        <h2 id="entry-title">Stock entry</h2>
        <button class="text-button hidden" id="cancel-edit" type="button">Cancel</button>
      </div>

      <form id="item-form" autocomplete="off">
        <input type="hidden" id="item-id" name="id" />

        <label class="field" for="sku">
          <span>Code</span>
          <input id="sku" name="sku" type="text" inputmode="text" maxlength="64" placeholder="AUTO-001" />
        </label>

        <label class="field" for="name">
          <span>Item</span>
          <input id="name" name="name" type="text" inputmode="text" required maxlength="160" placeholder="Car jack" />
        </label>

        <label class="field" for="location-code">
          <span>Bin</span>
          <input id="location-code" name="locationCode" type="text" required maxlength="80" list="locations" placeholder="GAR-S1-L" />
        </label>

        <label class="field" for="location-detail">
          <span>Location</span>
          <input id="location-detail" name="locationDetail" type="text" maxlength="160" list="location-details" placeholder="Garage shelves 1 lower" />
        </label>

        <label class="field" for="quantity">
          <span>Stock</span>
          <input id="quantity" name="quantity" type="number" inputmode="numeric" min="1" max="9999" value="1" />
        </label>

        <label class="field" for="category">
          <span>Category</span>
          <input id="category" name="category" type="text" maxlength="80" list="categories" placeholder="Tools" />
        </label>

        <label class="field field-full photo-field" for="photo">
          <span>Photo</span>
          <input id="photo" name="photo" type="file" accept="image/*" capture="environment" />
          <div class="photo-preview hidden" id="photo-preview">
            <img id="photo-preview-image" alt="" />
            <button class="small-button danger-button" id="remove-photo" type="button">Remove photo</button>
          </div>
        </label>

        <label class="field field-full" for="notes">
          <span>Notes</span>
          <textarea id="notes" name="notes" rows="3" maxlength="1000" placeholder="Size, condition, supplier, part number"></textarea>
        </label>

        <div class="form-actions">
          <button class="primary-button" id="save-button" type="submit">Save stock</button>
          <p class="status" id="save-status" role="status" aria-live="polite"></p>
        </div>
      </form>
    </section>

    <section class="search-panel" aria-labelledby="search-title">
      <div class="section-head search-head">
        <h2 id="search-title">Pick list</h2>
        <button class="text-button" id="clear-search" type="button">Clear</button>
      </div>

      <label class="field field-full search-field" for="search">
        <span>Search</span>
        <input id="search" type="search" inputmode="search" maxlength="120" placeholder="code, item, bin, hinge, inner tube" />
      </label>

      <div class="result-meta" aria-live="polite">
        <span id="result-count">0 results</span>
        <span id="last-updated">Not synced</span>
      </div>

      <div class="results" id="results"></div>
      <p class="empty-state hidden" id="empty-state">No items found.</p>
    </section>
  </main>

  <datalist id="locations"></datalist>
  <datalist id="categories"></datalist>

  <template id="item-template">
    <article class="item-card">
      <div class="item-main">
        <img class="item-photo hidden" alt="" loading="lazy" />
        <div>
          <p class="item-code hidden"></p>
          <h3 class="item-name"></h3>
          <p class="item-location"></p>
        </div>
        <span class="quantity"></span>
      </div>
      <div class="item-details">
        <span class="category hidden"></span>
        <span class="updated"></span>
      </div>
      <p class="notes hidden"></p>
      <div class="item-actions">
        <button class="small-button minus-button" type="button">-1</button>
        <button class="small-button plus-button" type="button">+1</button>
        <button class="small-button edit-button" type="button">Edit</button>
        <button class="small-button danger-button delete-button" type="button">Delete</button>
      </div>
    </article>
  </template>

  <datalist id="location-details"></datalist>

  <script src="app.js?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
