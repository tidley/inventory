<?php
$assetVersion = '2026-05-23-5';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Inventory</title>
  <meta name="description" content="Personal warehouse-style inventory tracker." />
  <meta name="theme-color" content="#101418" />
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
        <span><strong id="category-count">0</strong> categories</span>
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
          <select id="location-code" name="locationCode" required>
            <option value="">Select bin</option>
          </select>
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
          <select id="category" name="category">
            <option value="">No category</option>
          </select>
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

    <section class="bin-panel" aria-labelledby="bin-title">
      <div class="section-head">
        <h2 id="bin-title">Bins</h2>
        <button class="text-button hidden" id="cancel-bin-edit" type="button">Cancel</button>
      </div>

      <form id="bin-form" class="compact-form" autocomplete="off">
        <input type="hidden" id="bin-original-code" />
        <label class="field" for="bin-code">
          <span>Code</span>
          <input id="bin-code" type="text" inputmode="text" required maxlength="80" placeholder="GAR-S1-L" />
        </label>
        <label class="field" for="bin-label">
          <span>Label</span>
          <input id="bin-label" type="text" inputmode="text" maxlength="160" placeholder="Garage shelf 1 lower" />
        </label>
        <div class="form-actions">
          <button class="primary-button" id="save-bin-button" type="submit">Save bin</button>
          <p class="status" id="bin-status" role="status" aria-live="polite"></p>
        </div>
      </form>

      <div class="move-panel hidden" id="bin-move-panel">
        <label class="field" for="move-bin-target">
          <span id="move-bin-label">Move items to</span>
          <select id="move-bin-target"></select>
        </label>
        <div class="item-actions">
          <button class="small-button danger-button" id="confirm-bin-delete" type="button">Move and delete</button>
          <button class="small-button" id="cancel-bin-delete" type="button">Cancel</button>
        </div>
      </div>

      <div class="bin-list" id="bin-list"></div>
    </section>

    <section class="category-panel" aria-labelledby="category-title">
      <div class="section-head">
        <h2 id="category-title">Categories</h2>
        <button class="text-button hidden" id="cancel-category-edit" type="button">Cancel</button>
      </div>

      <form id="category-form" class="compact-form" autocomplete="off">
        <input type="hidden" id="category-original-code" />
        <label class="field" for="category-code">
          <span>Code</span>
          <input id="category-code" type="text" inputmode="text" required maxlength="80" placeholder="TOOLS" />
        </label>
        <label class="field" for="category-label">
          <span>Label</span>
          <input id="category-label" type="text" inputmode="text" maxlength="160" placeholder="Tools and hardware" />
        </label>
        <div class="form-actions">
          <button class="primary-button" id="save-category-button" type="submit">Save category</button>
          <p class="status" id="category-status" role="status" aria-live="polite"></p>
        </div>
      </form>

      <div class="move-panel hidden" id="category-move-panel">
        <label class="field" for="move-category-target">
          <span id="move-category-label">Move items to</span>
          <select id="move-category-target"></select>
        </label>
        <div class="item-actions">
          <button class="small-button danger-button" id="confirm-category-delete" type="button">Move and delete</button>
          <button class="small-button" id="cancel-category-delete" type="button">Cancel</button>
        </div>
      </div>

      <div class="bin-list" id="category-list"></div>
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

  <template id="category-template">
    <article class="bin-row">
      <div>
        <h3 class="category-code"></h3>
        <p class="category-label"></p>
      </div>
      <span class="category-count-row"></span>
      <div class="item-actions">
        <button class="small-button category-edit-button" type="button">Edit</button>
        <button class="small-button danger-button category-delete-button" type="button">Delete</button>
      </div>
    </article>
  </template>

  <template id="bin-template">
    <article class="bin-row">
      <div>
        <h3 class="bin-code"></h3>
        <p class="bin-label"></p>
      </div>
      <span class="bin-count"></span>
      <div class="item-actions">
        <button class="small-button bin-edit-button" type="button">Edit</button>
        <button class="small-button danger-button bin-delete-button" type="button">Delete</button>
      </div>
    </article>
  </template>

  <datalist id="location-details"></datalist>

  <script src="app.js?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
