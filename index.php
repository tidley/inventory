<?php
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/version.php';
$assetVersion = INVENTORY_VERSION . '-2026-05-23-13';
inventory_require_auth_page($assetVersion);
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

    <nav class="app-nav" aria-label="Inventory views">
      <button class="nav-button active" type="button" data-view-target="search">Search</button>
      <button class="nav-button" type="button" data-view-target="inventory">Inventory</button>
      <button class="nav-button" type="button" data-view-target="manage">Manage</button>
      <button class="nav-button" type="button" data-view-target="settings">Settings</button>
    </nav>

    <section class="entry-panel view-section" data-view="inventory" aria-labelledby="entry-title">
      <div class="section-head">
        <h2 id="entry-title">Stock entry</h2>
        <div class="section-actions">
          <button class="primary-button compact-primary" id="toggle-entry-form" type="button">Add stock</button>
          <button class="text-button hidden" id="cancel-edit" type="button">Cancel</button>
        </div>
      </div>

      <form id="item-form" class="hidden" autocomplete="off">
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

    <section class="bin-panel view-section" data-view="manage" aria-labelledby="bin-title">
      <div class="section-head">
        <h2 id="bin-title">Bins</h2>
        <div class="section-actions">
          <button class="primary-button compact-primary" id="toggle-bin-form" type="button">Add bin</button>
          <button class="text-button hidden" id="cancel-bin-edit" type="button">Cancel</button>
        </div>
      </div>

      <form id="bin-form" class="compact-form hidden" autocomplete="off">
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

    <section class="category-panel view-section" data-view="manage" aria-labelledby="category-title">
      <div class="section-head">
        <h2 id="category-title">Categories</h2>
        <div class="section-actions">
          <button class="primary-button compact-primary" id="toggle-category-form" type="button">Add category</button>
          <button class="text-button hidden" id="cancel-category-edit" type="button">Cancel</button>
        </div>
      </div>

      <form id="category-form" class="compact-form hidden" autocomplete="off">
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

    <section class="update-panel view-section" data-view="settings" aria-labelledby="update-title">
      <div class="section-head">
        <h2 id="update-title">Updates</h2>
        <button class="text-button" id="check-update-button" type="button">Check</button>
      </div>

      <div class="update-grid">
        <div>
          <span>Current</span>
          <strong id="update-current-version">v<?php echo htmlspecialchars(INVENTORY_VERSION, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
        <div>
          <span>Latest</span>
          <strong id="update-latest-version">Not checked</strong>
        </div>
      </div>

      <label class="field field-full" for="update-token">
        <span>Update token</span>
        <input id="update-token" type="password" autocomplete="off" placeholder="Required to install" />
      </label>

      <div class="form-actions update-actions">
        <button class="primary-button hidden" id="install-update-button" type="button" disabled>Update</button>
        <p class="status" id="update-status" role="status" aria-live="polite">Ready</p>
      </div>
    </section>

    <section class="security-panel view-section" data-view="settings" aria-labelledby="security-title">
      <div class="section-head">
        <h2 id="security-title">Access</h2>
        <button class="text-button" id="logout-button" type="button">Logout</button>
      </div>

      <div class="security-grid">
        <div>
          <span>User</span>
          <strong id="auth-current-user"><?php echo htmlspecialchars(inventory_current_username(), ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>
        <div>
          <span>Passkeys</span>
          <strong id="passkey-count">0</strong>
        </div>
      </div>

      <div class="form-actions update-actions">
        <button class="primary-button" id="enable-passkey-button" type="button">Enable device unlock</button>
        <p class="status" id="security-status" role="status" aria-live="polite"></p>
      </div>
    </section>

    <section class="search-panel view-section active-view" data-view="search" aria-labelledby="search-title">
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

      <div class="filter-chips" id="filter-chips" aria-label="Category filters"></div>

      <div class="results" id="results"></div>
      <p class="empty-state hidden" id="empty-state">No items found.</p>
    </section>
  </main>

  <p class="toast hidden" id="app-toast" role="status" aria-live="polite"></p>

  <dialog class="modal" id="quick-bin-dialog" aria-labelledby="quick-bin-title">
    <form id="quick-bin-form" class="modal-card" autocomplete="off">
      <div class="section-head">
        <h2 id="quick-bin-title">Add bin</h2>
        <button class="text-button" id="quick-bin-cancel" type="button">Cancel</button>
      </div>

      <label class="field" for="quick-bin-code">
        <span>Code</span>
        <input id="quick-bin-code" type="text" inputmode="text" required maxlength="80" placeholder="GAR-S1-L" />
      </label>

      <label class="field" for="quick-bin-label">
        <span>Label</span>
        <input id="quick-bin-label" type="text" inputmode="text" maxlength="160" placeholder="Garage shelf 1 lower" />
      </label>

      <div class="form-actions">
        <button class="primary-button" id="save-quick-bin-button" type="submit">Save bin</button>
        <p class="status" id="quick-bin-status" role="status" aria-live="polite"></p>
      </div>
    </form>
  </dialog>

  <dialog class="modal" id="quick-category-dialog" aria-labelledby="quick-category-title">
    <form id="quick-category-form" class="modal-card" autocomplete="off">
      <div class="section-head">
        <h2 id="quick-category-title">Add category</h2>
        <button class="text-button" id="quick-category-cancel" type="button">Cancel</button>
      </div>

      <label class="field" for="quick-category-code">
        <span>Code</span>
        <input id="quick-category-code" type="text" inputmode="text" required maxlength="80" placeholder="TOOLS" />
      </label>

      <label class="field" for="quick-category-label">
        <span>Label</span>
        <input id="quick-category-label" type="text" inputmode="text" maxlength="160" placeholder="Tools and hardware" />
      </label>

      <div class="form-actions">
        <button class="primary-button" id="save-quick-category-button" type="submit">Save category</button>
        <p class="status" id="quick-category-status" role="status" aria-live="polite"></p>
      </div>
    </form>
  </dialog>

  <dialog class="modal detail-modal" id="item-detail-dialog" aria-labelledby="item-detail-title">
    <article class="modal-card detail-card">
      <div class="section-head detail-head">
        <div>
          <p class="item-code hidden" id="item-detail-code"></p>
          <h2 id="item-detail-title"></h2>
          <p class="detail-subtitle" id="item-detail-subtitle"></p>
        </div>
        <button class="text-button" id="item-detail-close" type="button">Close</button>
      </div>

      <img class="detail-photo hidden" id="item-detail-photo" alt="" />

      <dl class="detail-grid">
        <div>
          <dt>Bin</dt>
          <dd id="item-detail-bin"></dd>
        </div>
        <div>
          <dt>Location</dt>
          <dd id="item-detail-location"></dd>
        </div>
        <div>
          <dt>Stock</dt>
          <dd id="item-detail-quantity"></dd>
        </div>
        <div>
          <dt>Category</dt>
          <dd id="item-detail-category"></dd>
        </div>
        <div>
          <dt>Created</dt>
          <dd id="item-detail-created"></dd>
        </div>
        <div>
          <dt>Updated</dt>
          <dd id="item-detail-updated"></dd>
        </div>
      </dl>

      <div class="detail-notes">
        <h3>Notes</h3>
        <p id="item-detail-notes"></p>
      </div>

      <div class="item-actions">
        <button class="small-button" id="item-detail-edit" type="button">Edit</button>
      </div>
    </article>
  </dialog>

  <template id="item-template">
    <article class="item-card">
      <div class="item-main">
        <img class="item-photo hidden" alt="" loading="lazy" />
        <div class="item-copy">
          <p class="item-code hidden"></p>
          <h3 class="item-name"></h3>
          <p class="item-location"></p>
          <div class="item-details">
            <span class="category hidden"></span>
            <span class="updated"></span>
          </div>
        </div>
        <span class="quantity"></span>
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
  <script src="auth.js?v=<?php echo htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
