const apiUrl = 'api.php';
const addBinOptionValue = '__add_bin__';
const updateTokenStorageKey = 'inventoryUpdateToken';
const maxPhotoDimension = 1280;
const photoQuality = 0.78;

const state = {
  items: [],
  meta: {},
  query: '',
  editingId: '',
  detailItemId: '',
  lastLocationCode: '',
  binEditingCode: '',
  pendingDeleteBin: null,
  categoryEditingCode: '',
  pendingDeleteCategory: null,
  removePhoto: false,
  previewUrl: '',
};

const ui = {
  form: document.getElementById('item-form'),
  itemId: document.getElementById('item-id'),
  sku: document.getElementById('sku'),
  name: document.getElementById('name'),
  locationCode: document.getElementById('location-code'),
  locationDetail: document.getElementById('location-detail'),
  quantity: document.getElementById('quantity'),
  category: document.getElementById('category'),
  photo: document.getElementById('photo'),
  photoPreview: document.getElementById('photo-preview'),
  photoPreviewImage: document.getElementById('photo-preview-image'),
  removePhoto: document.getElementById('remove-photo'),
  notes: document.getElementById('notes'),
  entryTitle: document.getElementById('entry-title'),
  saveButton: document.getElementById('save-button'),
  saveStatus: document.getElementById('save-status'),
  cancelEdit: document.getElementById('cancel-edit'),
  binForm: document.getElementById('bin-form'),
  binOriginalCode: document.getElementById('bin-original-code'),
  binCode: document.getElementById('bin-code'),
  binLabel: document.getElementById('bin-label'),
  saveBinButton: document.getElementById('save-bin-button'),
  binStatus: document.getElementById('bin-status'),
  cancelBinEdit: document.getElementById('cancel-bin-edit'),
  binList: document.getElementById('bin-list'),
  binTemplate: document.getElementById('bin-template'),
  binMovePanel: document.getElementById('bin-move-panel'),
  moveBinLabel: document.getElementById('move-bin-label'),
  moveBinTarget: document.getElementById('move-bin-target'),
  confirmBinDelete: document.getElementById('confirm-bin-delete'),
  cancelBinDelete: document.getElementById('cancel-bin-delete'),
  quickBinDialog: document.getElementById('quick-bin-dialog'),
  quickBinForm: document.getElementById('quick-bin-form'),
  quickBinCode: document.getElementById('quick-bin-code'),
  quickBinLabel: document.getElementById('quick-bin-label'),
  saveQuickBinButton: document.getElementById('save-quick-bin-button'),
  quickBinStatus: document.getElementById('quick-bin-status'),
  quickBinCancel: document.getElementById('quick-bin-cancel'),
  categoryForm: document.getElementById('category-form'),
  categoryOriginalCode: document.getElementById('category-original-code'),
  categoryCode: document.getElementById('category-code'),
  categoryLabel: document.getElementById('category-label'),
  saveCategoryButton: document.getElementById('save-category-button'),
  categoryStatus: document.getElementById('category-status'),
  cancelCategoryEdit: document.getElementById('cancel-category-edit'),
  categoryList: document.getElementById('category-list'),
  categoryTemplate: document.getElementById('category-template'),
  categoryMovePanel: document.getElementById('category-move-panel'),
  moveCategoryLabel: document.getElementById('move-category-label'),
  moveCategoryTarget: document.getElementById('move-category-target'),
  confirmCategoryDelete: document.getElementById('confirm-category-delete'),
  cancelCategoryDelete: document.getElementById('cancel-category-delete'),
  search: document.getElementById('search'),
  clearSearch: document.getElementById('clear-search'),
  results: document.getElementById('results'),
  emptyState: document.getElementById('empty-state'),
  resultCount: document.getElementById('result-count'),
  itemCount: document.getElementById('item-count'),
  unitCount: document.getElementById('unit-count'),
  locationCount: document.getElementById('location-count'),
  categoryCount: document.getElementById('category-count'),
  lastUpdated: document.getElementById('last-updated'),
  updateCurrentVersion: document.getElementById('update-current-version'),
  updateLatestVersion: document.getElementById('update-latest-version'),
  updateToken: document.getElementById('update-token'),
  checkUpdateButton: document.getElementById('check-update-button'),
  installUpdateButton: document.getElementById('install-update-button'),
  updateStatus: document.getElementById('update-status'),
  itemDetailDialog: document.getElementById('item-detail-dialog'),
  itemDetailTitle: document.getElementById('item-detail-title'),
  itemDetailCode: document.getElementById('item-detail-code'),
  itemDetailSubtitle: document.getElementById('item-detail-subtitle'),
  itemDetailPhoto: document.getElementById('item-detail-photo'),
  itemDetailBin: document.getElementById('item-detail-bin'),
  itemDetailLocation: document.getElementById('item-detail-location'),
  itemDetailQuantity: document.getElementById('item-detail-quantity'),
  itemDetailCategory: document.getElementById('item-detail-category'),
  itemDetailCreated: document.getElementById('item-detail-created'),
  itemDetailUpdated: document.getElementById('item-detail-updated'),
  itemDetailNotes: document.getElementById('item-detail-notes'),
  itemDetailClose: document.getElementById('item-detail-close'),
  itemDetailEdit: document.getElementById('item-detail-edit'),
  itemTemplate: document.getElementById('item-template'),
  locationDetails: document.getElementById('location-details'),
};

let latestUpdateInfo = null;
let updateCheckInFlight = false;

const formatUpdated = new Intl.DateTimeFormat('en-GB', {
  day: '2-digit',
  month: 'short',
  hour: '2-digit',
  minute: '2-digit',
});

const formatDetailDate = new Intl.DateTimeFormat('en-GB', {
  dateStyle: 'medium',
  timeStyle: 'short',
});

function setStatus(message, isError = false) {
  ui.saveStatus.textContent = message;
  ui.saveStatus.classList.toggle('error', isError);
}

async function request(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...(options.headers || {}),
  };
  if (options.body && !headers['Content-Type']) {
    headers['Content-Type'] = 'application/json';
  }

  const response = await fetch(path, {
    ...options,
    headers,
  });
  const raw = await response.text();
  let data = {};
  if (raw) {
    try {
      data = JSON.parse(raw);
    } catch (error) {
      data = {};
    }
  }
  if (!response.ok) {
    const error = new Error(data.error || `Server returned ${response.status}`);
    error.data = data;
    throw error;
  }
  return data;
}

function applyPayload(data) {
  state.items = data.items || [];
  state.meta = data.meta || {};
  updateDatalists();
  renderBinSelect();
  renderCategorySelect();
  renderBins();
  renderCategories();
  renderItems();
}

function itemMatchesQuery(item, query) {
  const tokens = query.toLowerCase().trim().split(/\s+/).filter(Boolean);
  if (!tokens.length) return true;
  const text = [
    item.sku,
    item.name,
    item.locationCode,
    item.locationDetail,
    item.category,
    item.notes,
  ].join(' ').toLowerCase();
  return tokens.every((token) => text.includes(token));
}

function visibleItems() {
  return state.items.filter((item) => itemMatchesQuery(item, state.query));
}

function optionsFrom(values = []) {
  return values.map((value) => {
    const option = document.createElement('option');
    option.value = value;
    return option;
  });
}

function updateDatalists() {
  ui.locationDetails.replaceChildren(...optionsFrom(state.meta.locationDetails || []));
}

function bins() {
  return state.meta.bins || [];
}

function binForCode(code) {
  return bins().find((bin) => bin.code === code);
}

function binDisplay(bin) {
  if (!bin) return '';
  return bin.label ? `${bin.code} · ${bin.label}` : bin.code;
}

function cleanCode(value, maxLength = 80) {
  return String(value || '').replace(/\s+/g, ' ').trim().slice(0, maxLength).toUpperCase();
}

function selectLocationCode(code) {
  const target = cleanCode(code);
  if (target && bins().some((bin) => bin.code === target)) {
    ui.locationCode.value = target;
    state.lastLocationCode = target;
    return;
  }

  ui.locationCode.value = '';
  state.lastLocationCode = '';
}

function categories() {
  return state.meta.managedCategories || [];
}

function categoryForCode(code) {
  return categories().find((category) => category.code === code);
}

function categoryDisplay(category) {
  if (!category) return '';
  return category.label ? `${category.code} · ${category.label}` : category.code;
}

function itemLocationDisplay(item) {
  const bin = binForCode(item.locationCode);
  const locationParts = [item.locationCode];
  if (bin && bin.label && bin.label !== item.locationCode) locationParts.push(bin.label);
  if (item.locationDetail) locationParts.push(item.locationDetail);
  return locationParts.filter(Boolean).join(' · ');
}

function itemBinDisplay(item) {
  const bin = binForCode(item.locationCode);
  if (bin) return binDisplay(bin);
  return item.locationCode || 'None';
}

function itemCategoryDisplay(item) {
  const category = categoryForCode(item.category);
  if (item.category && category) return categoryDisplay(category);
  return item.category || 'None';
}

function renderBinSelect() {
  const current = ui.locationCode.value === addBinOptionValue ? state.lastLocationCode : ui.locationCode.value;
  const options = [new Option('Select bin', '')];
  bins().forEach((bin) => {
    options.push(new Option(binDisplay(bin), bin.code));
  });
  options.push(new Option('Add bin...', addBinOptionValue));
  ui.locationCode.replaceChildren(...options);
  selectLocationCode(current);
}

function renderCategorySelect() {
  const current = ui.category.value;
  const options = [new Option('No category', '')];
  categories().forEach((category) => {
    options.push(new Option(categoryDisplay(category), category.code));
  });
  ui.category.replaceChildren(...options);
  if (categories().some((category) => category.code === current)) {
    ui.category.value = current;
  }
}

function renderMoveTargets(excludeCode) {
  const options = [new Option('Select bin', '')];
  bins()
    .filter((bin) => bin.code !== excludeCode)
    .forEach((bin) => {
      options.push(new Option(binDisplay(bin), bin.code));
    });
  ui.moveBinTarget.replaceChildren(...options);
}

function renderCategoryMoveTargets(excludeCode) {
  const options = [new Option('Select category', '')];
  categories()
    .filter((category) => category.code !== excludeCode)
    .forEach((category) => {
      options.push(new Option(categoryDisplay(category), category.code));
    });
  ui.moveCategoryTarget.replaceChildren(...options);
}

function displayTime(value) {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.valueOf()) ? '' : formatUpdated.format(date);
}

function displayDetailTime(value) {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.valueOf()) ? '' : formatDetailDate.format(date);
}

function updateStats(items) {
  const stats = state.meta.stats || {};
  ui.itemCount.textContent = String(stats.itemCount ?? state.items.length);
  ui.unitCount.textContent = String(stats.unitCount ?? state.items.reduce((sum, item) => sum + (item.quantity || 0), 0));
  ui.locationCount.textContent = String(stats.locationCount ?? 0);
  ui.categoryCount.textContent = String(stats.categoryCount ?? categories().length);
  ui.resultCount.textContent = `${items.length} ${items.length === 1 ? 'result' : 'results'}`;
  ui.lastUpdated.textContent = stats.lastUpdated ? `Updated ${displayTime(stats.lastUpdated)}` : 'Ready';
}

function renderItems() {
  const items = visibleItems();
  ui.results.replaceChildren();

  items.forEach((item) => {
    const fragment = ui.itemTemplate.content.cloneNode(true);
    const card = fragment.querySelector('.item-card');
    const photo = fragment.querySelector('.item-photo');
    const code = fragment.querySelector('.item-code');
    const category = fragment.querySelector('.category');
    const notes = fragment.querySelector('.notes');
    const location = itemLocationDisplay(item);

    card.dataset.id = item.id;
    card.tabIndex = 0;
    card.setAttribute('aria-label', `View ${item.name}`);
    fragment.querySelector('.item-name').textContent = item.name;
    fragment.querySelector('.item-location').textContent = location;
    fragment.querySelector('.quantity').textContent = `x${item.quantity || 1}`;
    fragment.querySelector('.updated').textContent = displayTime(item.updatedAt);

    if (item.hasPhoto && item.photoUrl) {
      photo.src = item.photoUrl;
      photo.classList.remove('hidden');
      card.classList.add('has-photo');
    }

    if (item.sku) {
      code.textContent = item.sku;
      code.classList.remove('hidden');
    }

    const itemCategory = categoryForCode(item.category);
    if (item.category) {
      category.textContent = itemCategoryDisplay(item);
      category.classList.remove('hidden');
    }

    if (item.notes) {
      notes.textContent = item.notes;
      notes.classList.remove('hidden');
    }

    fragment.querySelector('.minus-button').addEventListener('click', () => adjustItem(item.id, -1));
    fragment.querySelector('.plus-button').addEventListener('click', () => adjustItem(item.id, 1));
    fragment.querySelector('.edit-button').addEventListener('click', () => editItem(item.id));
    fragment.querySelector('.delete-button').addEventListener('click', () => deleteItem(item.id));
    card.addEventListener('click', (event) => {
      if (event.target instanceof Element && event.target.closest('button')) return;
      openItemDetail(item.id);
    });
    card.addEventListener('keydown', (event) => {
      if (event.target !== card || (event.key !== 'Enter' && event.key !== ' ')) return;
      event.preventDefault();
      openItemDetail(item.id);
    });
    ui.results.appendChild(fragment);
  });

  ui.emptyState.classList.toggle('hidden', items.length !== 0);
  updateStats(items);
}

function renderCategories() {
  ui.categoryList.replaceChildren();

  categories().forEach((category) => {
    const fragment = ui.categoryTemplate.content.cloneNode(true);
    fragment.querySelector('.category-code').textContent = category.code;
    fragment.querySelector('.category-label').textContent = category.label || '';
    fragment.querySelector('.category-count-row').textContent = `${category.itemCount || 0} ${category.itemCount === 1 ? 'item' : 'items'}`;
    fragment.querySelector('.category-edit-button').addEventListener('click', () => editCategory(category.code));
    fragment.querySelector('.category-delete-button').addEventListener('click', () => deleteCategory(category.code));
    ui.categoryList.appendChild(fragment);
  });
}

function renderBins() {
  ui.binList.replaceChildren();

  bins().forEach((bin) => {
    const fragment = ui.binTemplate.content.cloneNode(true);
    fragment.querySelector('.bin-code').textContent = bin.code;
    fragment.querySelector('.bin-label').textContent = bin.label || '';
    fragment.querySelector('.bin-count').textContent = `${bin.itemCount || 0} ${bin.itemCount === 1 ? 'item' : 'items'}`;
    fragment.querySelector('.bin-edit-button').addEventListener('click', () => editBin(bin.code));
    fragment.querySelector('.bin-delete-button').addEventListener('click', () => deleteBin(bin.code));
    ui.binList.appendChild(fragment);
  });
}

function revokePreviewUrl() {
  if (state.previewUrl) {
    URL.revokeObjectURL(state.previewUrl);
    state.previewUrl = '';
  }
}

function hidePhotoPreview() {
  revokePreviewUrl();
  ui.photoPreviewImage.removeAttribute('src');
  ui.photoPreview.classList.add('hidden');
}

function showPhotoPreview(src) {
  ui.photoPreviewImage.src = src;
  ui.photoPreview.classList.remove('hidden');
}

function resetForm() {
  state.editingId = '';
  state.removePhoto = false;
  ui.form.reset();
  ui.itemId.value = '';
  selectLocationCode('');
  ui.quantity.value = '1';
  ui.entryTitle.textContent = 'Stock entry';
  ui.saveButton.textContent = 'Save stock';
  ui.cancelEdit.classList.add('hidden');
  hidePhotoPreview();
  setStatus('');
}

function setBinStatus(message, isError = false) {
  ui.binStatus.textContent = message;
  ui.binStatus.classList.toggle('error', isError);
}

function resetBinForm() {
  state.binEditingCode = '';
  ui.binForm.reset();
  ui.binOriginalCode.value = '';
  ui.saveBinButton.textContent = 'Save bin';
  ui.cancelBinEdit.classList.add('hidden');
  setBinStatus('');
}

function setQuickBinStatus(message, isError = false) {
  ui.quickBinStatus.textContent = message;
  ui.quickBinStatus.classList.toggle('error', isError);
}

function openQuickBinDialog() {
  ui.quickBinForm.reset();
  setQuickBinStatus('');
  ui.saveQuickBinButton.disabled = false;

  if (typeof ui.quickBinDialog.showModal === 'function') {
    ui.quickBinDialog.showModal();
  } else {
    ui.quickBinDialog.setAttribute('open', '');
  }

  setTimeout(() => ui.quickBinCode.focus(), 0);
}

function closeQuickBinDialog() {
  if (ui.quickBinDialog.open && typeof ui.quickBinDialog.close === 'function') {
    ui.quickBinDialog.close();
  } else {
    ui.quickBinDialog.removeAttribute('open');
  }
  ui.quickBinForm.reset();
  setQuickBinStatus('');
}

function openItemDetail(id) {
  const item = state.items.find((candidate) => Number(candidate.id) === Number(id));
  if (!item) return;

  state.detailItemId = item.id;
  ui.itemDetailTitle.textContent = item.name;
  ui.itemDetailSubtitle.textContent = itemLocationDisplay(item) || 'No location';
  ui.itemDetailBin.textContent = itemBinDisplay(item);
  ui.itemDetailLocation.textContent = item.locationDetail || 'None';
  ui.itemDetailQuantity.textContent = String(item.quantity || 1);
  ui.itemDetailCategory.textContent = itemCategoryDisplay(item);
  ui.itemDetailCreated.textContent = displayDetailTime(item.createdAt) || 'Unknown';
  ui.itemDetailUpdated.textContent = displayDetailTime(item.updatedAt) || 'Unknown';
  ui.itemDetailNotes.textContent = item.notes || 'None';

  if (item.sku) {
    ui.itemDetailCode.textContent = item.sku;
    ui.itemDetailCode.classList.remove('hidden');
  } else {
    ui.itemDetailCode.textContent = '';
    ui.itemDetailCode.classList.add('hidden');
  }

  if (item.hasPhoto && item.photoUrl) {
    ui.itemDetailPhoto.src = item.photoUrl;
    ui.itemDetailPhoto.alt = item.name;
    ui.itemDetailPhoto.classList.remove('hidden');
  } else {
    ui.itemDetailPhoto.removeAttribute('src');
    ui.itemDetailPhoto.alt = '';
    ui.itemDetailPhoto.classList.add('hidden');
  }

  if (typeof ui.itemDetailDialog.showModal === 'function') {
    ui.itemDetailDialog.showModal();
  } else {
    ui.itemDetailDialog.setAttribute('open', '');
  }
}

function closeItemDetail() {
  state.detailItemId = '';
  ui.itemDetailPhoto.removeAttribute('src');

  if (ui.itemDetailDialog.open && typeof ui.itemDetailDialog.close === 'function') {
    ui.itemDetailDialog.close();
  } else {
    ui.itemDetailDialog.removeAttribute('open');
  }
}

function editItemFromDetail() {
  const id = state.detailItemId;
  closeItemDetail();
  if (id) editItem(id);
}

function setCategoryStatus(message, isError = false) {
  ui.categoryStatus.textContent = message;
  ui.categoryStatus.classList.toggle('error', isError);
}

function resetCategoryForm() {
  state.categoryEditingCode = '';
  ui.categoryForm.reset();
  ui.categoryOriginalCode.value = '';
  ui.saveCategoryButton.textContent = 'Save category';
  ui.cancelCategoryEdit.classList.add('hidden');
  setCategoryStatus('');
}

function editBin(code) {
  const bin = binForCode(code);
  if (!bin) return;
  state.binEditingCode = bin.code;
  ui.binOriginalCode.value = bin.code;
  ui.binCode.value = bin.code;
  ui.binLabel.value = bin.label || '';
  ui.saveBinButton.textContent = 'Update bin';
  ui.cancelBinEdit.classList.remove('hidden');
  setBinStatus('');
  ui.binCode.focus();
}

function editCategory(code) {
  const category = categoryForCode(code);
  if (!category) return;
  state.categoryEditingCode = category.code;
  ui.categoryOriginalCode.value = category.code;
  ui.categoryCode.value = category.code;
  ui.categoryLabel.value = category.label || '';
  ui.saveCategoryButton.textContent = 'Update category';
  ui.cancelCategoryEdit.classList.remove('hidden');
  setCategoryStatus('');
  ui.categoryCode.focus();
}

function editItem(id) {
  const item = state.items.find((candidate) => Number(candidate.id) === Number(id));
  if (!item) return;

  state.editingId = item.id;
  state.removePhoto = false;
  ui.itemId.value = item.id;
  ui.sku.value = item.sku || '';
  ui.name.value = item.name || '';
  selectLocationCode(item.locationCode || '');
  ui.locationDetail.value = item.locationDetail || '';
  ui.quantity.value = item.quantity || 1;
  ui.category.value = item.category || '';
  ui.notes.value = item.notes || '';
  ui.photo.value = '';
  ui.entryTitle.textContent = 'Edit stock';
  ui.saveButton.textContent = 'Update stock';
  ui.cancelEdit.classList.remove('hidden');
  setStatus('');

  if (item.hasPhoto && item.photoUrl) {
    showPhotoPreview(item.photoUrl);
  } else {
    hidePhotoPreview();
  }

  document.querySelector('.entry-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
  ui.name.focus({ preventScroll: true });
}

function binPayload() {
  return {
    action: state.binEditingCode ? 'updateBin' : 'createBin',
    originalCode: state.binEditingCode,
    code: ui.binCode.value,
    label: ui.binLabel.value,
  };
}

function quickBinPayload() {
  return {
    action: 'createBin',
    code: ui.quickBinCode.value,
    label: ui.quickBinLabel.value,
  };
}

function categoryPayload() {
  return {
    action: state.categoryEditingCode ? 'updateCategory' : 'createCategory',
    originalCode: state.categoryEditingCode,
    code: ui.categoryCode.value,
    label: ui.categoryLabel.value,
  };
}

async function saveBin(event) {
  event.preventDefault();
  ui.saveBinButton.disabled = true;
  setBinStatus('Saving...');

  try {
    const data = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify(binPayload()),
    });
    resetBinForm();
    applyPayload(data);
    setBinStatus('Saved');
  } catch (error) {
    setBinStatus(error.message, true);
  } finally {
    ui.saveBinButton.disabled = false;
  }
}

async function saveQuickBin(event) {
  event.preventDefault();
  const createdCode = cleanCode(ui.quickBinCode.value);
  ui.saveQuickBinButton.disabled = true;
  setQuickBinStatus('Saving...');

  try {
    const data = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify(quickBinPayload()),
    });
    applyPayload(data);
    selectLocationCode(createdCode);
    closeQuickBinDialog();
    setStatus('Bin added');
  } catch (error) {
    setQuickBinStatus(error.message, true);
  } finally {
    ui.saveQuickBinButton.disabled = false;
  }
}

async function saveCategory(event) {
  event.preventDefault();
  ui.saveCategoryButton.disabled = true;
  setCategoryStatus('Saving...');

  try {
    const data = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify(categoryPayload()),
    });
    resetCategoryForm();
    applyPayload(data);
    setCategoryStatus('Saved');
  } catch (error) {
    setCategoryStatus(error.message, true);
  } finally {
    ui.saveCategoryButton.disabled = false;
  }
}

function showMovePanel(code, itemCount) {
  state.pendingDeleteBin = { code, itemCount };
  ui.moveBinLabel.textContent = `Move ${itemCount} ${itemCount === 1 ? 'item' : 'items'} from ${code} to`;
  renderMoveTargets(code);
  ui.binMovePanel.classList.remove('hidden');
  ui.moveBinTarget.focus();
}

function hideMovePanel() {
  state.pendingDeleteBin = null;
  ui.binMovePanel.classList.add('hidden');
  ui.moveBinTarget.replaceChildren();
}

function showCategoryMovePanel(code, itemCount) {
  state.pendingDeleteCategory = { code, itemCount };
  ui.moveCategoryLabel.textContent = `Move ${itemCount} ${itemCount === 1 ? 'item' : 'items'} from ${code} to`;
  renderCategoryMoveTargets(code);
  ui.categoryMovePanel.classList.remove('hidden');
  ui.moveCategoryTarget.focus();
}

function hideCategoryMovePanel() {
  state.pendingDeleteCategory = null;
  ui.categoryMovePanel.classList.add('hidden');
  ui.moveCategoryTarget.replaceChildren();
}

async function deleteBin(code) {
  if (!window.confirm(`Delete bin "${code}"?`)) return;

  try {
    const data = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify({ action: 'deleteBin', code }),
    });
    hideMovePanel();
    applyPayload(data);
    setBinStatus('Deleted');
  } catch (error) {
    if (error.data && error.data.requiresMove) {
      state.meta = error.data.meta || state.meta;
      renderMoveTargets(code);
      showMovePanel(error.data.binCode || code, error.data.itemCount || 0);
      setBinStatus(error.message, true);
      return;
    }
    setBinStatus(error.message, true);
  }
}

async function confirmMoveAndDeleteBin() {
  if (!state.pendingDeleteBin) return;
  const moveTo = ui.moveBinTarget.value;
  if (!moveTo) {
    setBinStatus('Choose another bin first', true);
    return;
  }

  try {
    const data = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify({
        action: 'deleteBin',
        code: state.pendingDeleteBin.code,
        moveTo,
      }),
    });
    hideMovePanel();
    applyPayload(data);
    setBinStatus('Moved and deleted');
  } catch (error) {
    setBinStatus(error.message, true);
  }
}

async function deleteCategory(code) {
  if (!window.confirm(`Delete category "${code}"?`)) return;

  try {
    const data = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify({ action: 'deleteCategory', code }),
    });
    hideCategoryMovePanel();
    applyPayload(data);
    setCategoryStatus('Deleted');
  } catch (error) {
    if (error.data && error.data.requiresMove) {
      state.meta = error.data.meta || state.meta;
      renderCategoryMoveTargets(code);
      showCategoryMovePanel(error.data.categoryCode || code, error.data.itemCount || 0);
      setCategoryStatus(error.message, true);
      return;
    }
    setCategoryStatus(error.message, true);
  }
}

async function confirmMoveAndDeleteCategory() {
  if (!state.pendingDeleteCategory) return;
  const moveTo = ui.moveCategoryTarget.value;
  if (!moveTo) {
    setCategoryStatus('Choose another category first', true);
    return;
  }

  try {
    const data = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify({
        action: 'deleteCategory',
        code: state.pendingDeleteCategory.code,
        moveTo,
      }),
    });
    hideCategoryMovePanel();
    applyPayload(data);
    setCategoryStatus('Moved and deleted');
  } catch (error) {
    setCategoryStatus(error.message, true);
  }
}

function blobToDataUrl(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ''));
    reader.onerror = () => reject(reader.error || new Error('Photo read failed'));
    reader.readAsDataURL(blob);
  });
}

function loadImage(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const image = new Image();
    image.onload = () => {
      URL.revokeObjectURL(url);
      resolve(image);
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('Photo could not be loaded'));
    };
    image.src = url;
  });
}

async function resizePhoto(file) {
  const image = await loadImage(file);
  const scale = Math.min(1, maxPhotoDimension / Math.max(image.naturalWidth, image.naturalHeight));
  const width = Math.max(1, Math.round(image.naturalWidth * scale));
  const height = Math.max(1, Math.round(image.naturalHeight * scale));
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  const context = canvas.getContext('2d', { alpha: false });
  context.drawImage(image, 0, 0, width, height);

  const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', photoQuality));
  if (!blob) {
    throw new Error('Photo could not be compressed');
  }

  const dataUrl = await blobToDataUrl(blob);
  return {
    photoMime: blob.type || 'image/jpeg',
    photoData: dataUrl.split(',')[1] || '',
  };
}

async function formPayload() {
  const payload = {
    action: state.editingId ? 'update' : 'create',
    id: state.editingId,
    sku: ui.sku.value,
    name: ui.name.value,
    locationCode: ui.locationCode.value === addBinOptionValue ? '' : ui.locationCode.value,
    locationDetail: ui.locationDetail.value,
    quantity: ui.quantity.value,
    category: ui.category.value,
    notes: ui.notes.value,
  };

  const [file] = ui.photo.files;
  if (file) {
    Object.assign(payload, await resizePhoto(file));
  } else if (state.removePhoto) {
    payload.removePhoto = true;
  }

  return payload;
}

async function saveItem(event) {
  event.preventDefault();
  ui.saveButton.disabled = true;
  setStatus(ui.photo.files.length ? 'Compressing photo...' : 'Saving...');

  try {
    const payload = await formPayload();
    setStatus('Saving...');
    const data = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    resetForm();
    applyPayload(data);
    setStatus('Saved');
  } catch (error) {
    setStatus(error.message, true);
  } finally {
    ui.saveButton.disabled = false;
  }
}

async function adjustItem(id, delta) {
  try {
    const data = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify({ action: 'adjust', id, delta }),
    });
    applyPayload(data);
  } catch (error) {
    setStatus(error.message, true);
  }
}

async function deleteItem(id) {
  const item = state.items.find((candidate) => Number(candidate.id) === Number(id));
  if (!item) return;
  if (!window.confirm(`Delete "${item.name}"?`)) return;

  try {
    const data = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify({ action: 'delete', id }),
    });
    if (Number(state.editingId) === Number(id)) resetForm();
    applyPayload(data);
    setStatus('Deleted');
  } catch (error) {
    setStatus(error.message, true);
  }
}

function handleLocationCodeChange() {
  if (ui.locationCode.value === addBinOptionValue) {
    selectLocationCode(state.lastLocationCode);
    openQuickBinDialog();
    return;
  }

  state.lastLocationCode = ui.locationCode.value;
}

function setUpdateStatus(message, isError = false) {
  ui.updateStatus.textContent = message;
  ui.updateStatus.classList.toggle('error', isError);
}

function loadSavedUpdateToken() {
  try {
    ui.updateToken.value = localStorage.getItem(updateTokenStorageKey) || '';
  } catch (error) {
    ui.updateToken.value = '';
  }
}

function saveUpdateToken() {
  try {
    const token = ui.updateToken.value.trim();
    if (token) {
      localStorage.setItem(updateTokenStorageKey, token);
    } else {
      localStorage.removeItem(updateTokenStorageKey);
    }
  } catch (error) {
    // Local storage is optional; the token can still be used for this request.
  }
}

function updateButtonForStatus(result) {
  ui.updateLatestVersion.textContent = result.latestTag || (result.latestVersion ? `v${result.latestVersion}` : 'Unknown');

  if (!result.updateAvailable) {
    ui.installUpdateButton.classList.add('hidden');
    ui.installUpdateButton.disabled = true;
    setUpdateStatus('You are running the latest version.');
    return;
  }

  if (!result.zipAvailable) {
    ui.installUpdateButton.classList.add('hidden');
    ui.installUpdateButton.disabled = true;
    setUpdateStatus('PHP ZipArchive is required to install updates.', true);
    return;
  }

  if (!result.installEnabled) {
    ui.installUpdateButton.classList.add('hidden');
    ui.installUpdateButton.disabled = true;
    setUpdateStatus('Set UPDATE_TOKEN in .env before installing.', true);
    return;
  }

  ui.installUpdateButton.classList.remove('hidden');
  ui.installUpdateButton.disabled = false;
  setUpdateStatus(`${result.latestTag || 'A new version'} is available.`);
}

async function checkForUpdate() {
  if (updateCheckInFlight) return latestUpdateInfo;
  updateCheckInFlight = true;
  ui.checkUpdateButton.disabled = true;
  ui.installUpdateButton.disabled = true;
  setUpdateStatus('Checking for updates...');

  try {
    const result = await request(apiUrl, {
      method: 'POST',
      body: JSON.stringify({ action: 'updateStatus' }),
    });
    latestUpdateInfo = result;
    updateButtonForStatus(result);
    return result;
  } catch (error) {
    latestUpdateInfo = null;
    ui.updateLatestVersion.textContent = 'Unavailable';
    ui.installUpdateButton.classList.add('hidden');
    setUpdateStatus(error.message, true);
    return null;
  } finally {
    updateCheckInFlight = false;
    ui.checkUpdateButton.disabled = false;
  }
}

async function installUpdate() {
  saveUpdateToken();
  const token = ui.updateToken.value.trim();
  if (!token) {
    setUpdateStatus('Update token is required.', true);
    ui.updateToken.focus();
    return;
  }

  let updateInfo = latestUpdateInfo;
  if (!updateInfo || !updateInfo.updateAvailable) {
    updateInfo = await checkForUpdate();
  }
  if (!updateInfo || !updateInfo.updateAvailable) return;

  const label = updateInfo.latestTag || `v${updateInfo.latestVersion}`;
  if (!window.confirm(`Install ${label}? Current files will be backed up first.`)) {
    return;
  }

  ui.checkUpdateButton.disabled = true;
  ui.installUpdateButton.disabled = true;
  ui.installUpdateButton.textContent = 'Updating...';
  setUpdateStatus('Downloading and installing update...');

  try {
    const result = await request(apiUrl, {
      method: 'POST',
      headers: {
        'X-Inventory-Update-Token': token,
      },
      body: JSON.stringify({ action: 'installUpdate' }),
    });

    if (result.updated) {
      setUpdateStatus(`Updated to ${result.tag || label}. Reloading...`);
      setTimeout(() => window.location.reload(), 1500);
    } else {
      setUpdateStatus(result.message || 'Already up to date.');
      ui.installUpdateButton.classList.add('hidden');
    }
  } catch (error) {
    setUpdateStatus(error.message, true);
  } finally {
    ui.checkUpdateButton.disabled = false;
    ui.installUpdateButton.disabled = false;
    ui.installUpdateButton.textContent = 'Update';
  }
}

async function loadItems() {
  try {
    const data = await request(`${apiUrl}?q=`);
    applyPayload(data);
  } catch (error) {
    ui.lastUpdated.textContent = 'Offline';
    setStatus(error.message, true);
  }
}

ui.form.addEventListener('submit', saveItem);
ui.cancelEdit.addEventListener('click', resetForm);
ui.locationCode.addEventListener('change', handleLocationCodeChange);
ui.binForm.addEventListener('submit', saveBin);
ui.cancelBinEdit.addEventListener('click', resetBinForm);
ui.confirmBinDelete.addEventListener('click', confirmMoveAndDeleteBin);
ui.cancelBinDelete.addEventListener('click', hideMovePanel);
ui.quickBinForm.addEventListener('submit', saveQuickBin);
ui.quickBinCancel.addEventListener('click', closeQuickBinDialog);
ui.quickBinDialog.addEventListener('click', (event) => {
  if (event.target === ui.quickBinDialog) closeQuickBinDialog();
});
ui.itemDetailClose.addEventListener('click', closeItemDetail);
ui.itemDetailEdit.addEventListener('click', editItemFromDetail);
ui.itemDetailDialog.addEventListener('click', (event) => {
  if (event.target === ui.itemDetailDialog) closeItemDetail();
});
ui.itemDetailDialog.addEventListener('close', () => {
  state.detailItemId = '';
  ui.itemDetailPhoto.removeAttribute('src');
});
ui.categoryForm.addEventListener('submit', saveCategory);
ui.cancelCategoryEdit.addEventListener('click', resetCategoryForm);
ui.confirmCategoryDelete.addEventListener('click', confirmMoveAndDeleteCategory);
ui.cancelCategoryDelete.addEventListener('click', hideCategoryMovePanel);
ui.checkUpdateButton.addEventListener('click', checkForUpdate);
ui.installUpdateButton.addEventListener('click', installUpdate);
ui.updateToken.addEventListener('change', saveUpdateToken);

ui.photo.addEventListener('change', () => {
  state.removePhoto = false;
  revokePreviewUrl();
  const [file] = ui.photo.files;
  if (!file) {
    hidePhotoPreview();
    return;
  }
  state.previewUrl = URL.createObjectURL(file);
  showPhotoPreview(state.previewUrl);
});

ui.removePhoto.addEventListener('click', () => {
  ui.photo.value = '';
  state.removePhoto = true;
  hidePhotoPreview();
  setStatus('Photo will be removed when saved');
});

ui.search.addEventListener('input', () => {
  state.query = ui.search.value;
  renderItems();
});

ui.clearSearch.addEventListener('click', () => {
  ui.search.value = '';
  state.query = '';
  renderItems();
  ui.search.focus();
});

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('sw.js').catch((error) => {
      console.warn('Service worker registration failed', error);
    });
  });
}

loadSavedUpdateToken();
loadItems();
