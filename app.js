const apiUrl = 'api.php';
const maxPhotoDimension = 1280;
const photoQuality = 0.78;

const state = {
  items: [],
  meta: {},
  query: '',
  editingId: '',
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
  search: document.getElementById('search'),
  clearSearch: document.getElementById('clear-search'),
  results: document.getElementById('results'),
  emptyState: document.getElementById('empty-state'),
  resultCount: document.getElementById('result-count'),
  itemCount: document.getElementById('item-count'),
  unitCount: document.getElementById('unit-count'),
  locationCount: document.getElementById('location-count'),
  lastUpdated: document.getElementById('last-updated'),
  itemTemplate: document.getElementById('item-template'),
  locations: document.getElementById('locations'),
  locationDetails: document.getElementById('location-details'),
  categories: document.getElementById('categories'),
};

const formatUpdated = new Intl.DateTimeFormat('en-GB', {
  day: '2-digit',
  month: 'short',
  hour: '2-digit',
  minute: '2-digit',
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
  const data = await response.json().catch(() => ({}));
  if (!response.ok) {
    throw new Error(data.error || 'Request failed');
  }
  return data;
}

function applyPayload(data) {
  state.items = data.items || [];
  state.meta = data.meta || {};
  updateDatalists();
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
  ui.locations.replaceChildren(...optionsFrom(state.meta.locations || []));
  ui.locationDetails.replaceChildren(...optionsFrom(state.meta.locationDetails || []));
  ui.categories.replaceChildren(...optionsFrom(state.meta.categories || []));
}

function displayTime(value) {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.valueOf()) ? '' : formatUpdated.format(date);
}

function updateStats(items) {
  const stats = state.meta.stats || {};
  ui.itemCount.textContent = String(stats.itemCount ?? state.items.length);
  ui.unitCount.textContent = String(stats.unitCount ?? state.items.reduce((sum, item) => sum + (item.quantity || 0), 0));
  ui.locationCount.textContent = String(stats.locationCount ?? 0);
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
    const location = [item.locationCode, item.locationDetail].filter(Boolean).join(' · ');

    card.dataset.id = item.id;
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

    if (item.category) {
      category.textContent = item.category;
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
    ui.results.appendChild(fragment);
  });

  ui.emptyState.classList.toggle('hidden', items.length !== 0);
  updateStats(items);
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
  ui.quantity.value = '1';
  ui.entryTitle.textContent = 'Stock entry';
  ui.saveButton.textContent = 'Save stock';
  ui.cancelEdit.classList.add('hidden');
  hidePhotoPreview();
  setStatus('');
}

function editItem(id) {
  const item = state.items.find((candidate) => Number(candidate.id) === Number(id));
  if (!item) return;

  state.editingId = item.id;
  state.removePhoto = false;
  ui.itemId.value = item.id;
  ui.sku.value = item.sku || '';
  ui.name.value = item.name || '';
  ui.locationCode.value = item.locationCode || '';
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
    locationCode: ui.locationCode.value,
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

loadItems();
