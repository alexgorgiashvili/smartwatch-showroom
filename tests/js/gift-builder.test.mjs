import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const dom = new JSDOM('<!doctype html><html><body></body></html>', { url: 'https://example.test' });
globalThis.window = dom.window;
globalThis.document = dom.window.document;
Object.defineProperty(globalThis, 'navigator', { value: dom.window.navigator, configurable: true });

const {
	chooseGiftRenderer,
	rendererForGiftTap,
	nextGiftOpenState,
} = await import('../../resources/js/gift-box-experience.js');
const {
	GIFT_DRAFT_KEY,
	GIFT_DRAFT_TTL,
	chooseBuilderRestore,
	giftSlotAssignments,
	isCurrentPriceRequest,
	readGiftDraft,
	safeAnalyticsPayload,
} = await import('../../resources/js/gift-builder.js');

test('renderer tiers respect accessibility and network constraints', () => {
	assert.equal(chooseGiftRenderer({ reducedMotion: true, webgl: true }), 'static');
	assert.equal(chooseGiftRenderer({ saveData: true, webgl: true }), 'css');
	assert.equal(chooseGiftRenderer({ effectiveType: '2g', webgl: true }), 'css');
	assert.equal(chooseGiftRenderer({ webgl: false }), 'css');
	assert.equal(chooseGiftRenderer({ webgl: true }), 'three');
});

test('tap before Three.js is ready uses the immediate CSS tier', () => {
	assert.equal(rendererForGiftTap('three', false), 'css');
	assert.equal(rendererForGiftTap('three', true), 'three');
});

test('opening can complete and replay without autoplay state', () => {
	assert.equal(nextGiftOpenState('static_closed', 'tap'), 'opening');
	assert.equal(nextGiftOpenState('opening', 'tap'), 'opening');
	assert.equal(nextGiftOpenState('opening', 'complete'), 'open');
	assert.equal(nextGiftOpenState('open', 'tap'), 'replay');
});

test('versioned drafts expire after 24 hours', () => {
	const storage = dom.window.sessionStorage;
	storage.setItem(GIFT_DRAFT_KEY, JSON.stringify({ version: 2, savedAt: 1, step: 1 }));
	assert.equal(readGiftDraft(storage, 1 + GIFT_DRAFT_TTL + 1), null);
	assert.equal(storage.getItem(GIFT_DRAFT_KEY), null);
});

test('direct presets win while an older draft is offered separately', () => {
	assert.deepEqual(chooseBuilderRestore({ hasDirectSelection: true, recommendation: { id: 1 }, draft: { id: 2 } }), { apply: null, offerDraft: true });
	assert.deepEqual(chooseBuilderRestore({ recommendation: { id: 1 }, draft: { id: 2 } }), { apply: 'recommendation', offerDraft: false });
	assert.deepEqual(chooseBuilderRestore({ draft: { id: 2 } }), { apply: 'draft', offerDraft: false });
});

test('analytics allowlist strips message and personal fields', () => {
	assert.deepEqual(safeAnalyticsPayload({ renderer: 'css', priority: 'best_price', message: 'private', recipient_name: 'private' }), { renderer: 'css', priority: 'best_price' });
});

test('live gift-box slots retain the main item and leave unused addon slots explicit', () => {
	const main = { role: 'main', variant: { id: 11 } };
	const addonOne = { role: 'addon', variant: { id: 21 } };
	const addonTwo = { role: 'addon', variant: { id: 22 } };
	assert.deepEqual(giftSlotAssignments([addonOne, main, addonTwo], 4), [main, addonOne, addonTwo, null]);
	assert.deepEqual(giftSlotAssignments([addonOne, main, addonTwo, { role: 'addon', variant: { id: 23 } }, { role: 'addon', variant: { id: 24 } }], 4), [main, addonOne, addonTwo, { role: 'addon', variant: { id: 23 } }]);
});

test('only the most recent pricing request can update the builder state', () => {
	assert.equal(isCurrentPriceRequest(4, 4), true);
	assert.equal(isCurrentPriceRequest(3, 4), false);
	assert.equal(isCurrentPriceRequest('5', 5), true);
});

test('builder selection patches the product card and live slot without replacing their nodes', async () => {
	const originalFetch = globalThis.fetch;
	window.matchMedia = () => ({ matches: true, addEventListener() {}, removeEventListener() {} });
	document.body.innerHTML = `
		<div data-gift-builder-experience>
			<div id="gift-preset-overview" hidden></div><div id="gift-draft-restore" hidden></div>
			<div id="gift-builder-app"><nav id="gift-progress"></nav><section id="gift-live-preview"><span data-gift-live-count></span><span data-gift-live-status></span><div data-gift-live-box><div data-gift-slot="main"><div class="gift-live-slot-media"></div></div><div data-gift-slot="addon-1"><div class="gift-live-slot-media"></div></div><div data-gift-slot="addon-2"><div class="gift-live-slot-media"></div></div><div data-gift-slot="addon-3"><div class="gift-live-slot-media"></div></div></div></section><div id="gift-content"></div><aside id="gift-summary"></aside></div>
			<div id="gift-mobile-bar"></div>
			<div id="gift-builder-sheet"><div data-builder-sheet-title></div><div data-builder-sheet-body></div><div data-builder-sheet-status></div></div>
			<div data-gift-completion hidden></div>
		</div>
		<script id="gift-builder-config" type="application/json">${JSON.stringify({
			builder: {
				maxItems: 4,
				messageMaxLength: 300,
				budgetBands: [{ slug: 'all', label: 'All', min: null, max: null }],
				packaging: [{ slug: 'standard', label: 'Standard', price: 0, capacity_units: 4 }],
				products: [{ id: 1, name: 'Watch', price: 100, price_formatted: '100.00 ₾', image: '/watch.jpg', role: 'main', variants: [{ id: 11, name: 'Blue', color_name: 'Blue', thumbnail_image: '/watch-blue.jpg' }, { id: 12, name: 'Pink', color_name: 'Pink', thumbnail_image: '/watch-pink.jpg' }] }],
				initial: {},
				routes: { price: '/gift-box-builder/price', addToCart: '/gift-box-builder/add-to-cart', cart: '/cart' },
			},
			i18n: { step_watch: 'Watch', step_addons: 'Add-ons', step_finish: 'Finish', step_of: 'Step :current / :total', progress_label: 'Progress', watch_title: 'Choose watch', watch_text: 'Choose one', available_count: ':count available', budget_filter: 'Budget', details: 'Details', choose_color: 'Color', no_products: 'No products', no_addons: 'No add-ons', addon: 'Add-on', main_gift: 'Main gift', choose_main: 'Choose main', your_box: 'Your box', summary: 'Summary', products_total: 'Products', packaging: 'Packaging', gift_discount: 'Discount', secure_checkout: 'Secure', rechecking_price: 'Checking', add_box: 'Add box', adding: 'Adding', my_box: 'My box', next: 'Next', add_to_cart: 'Add', back: 'Back', continue: 'Continue', free: 'Free' },
			common: { total: 'Total', free: 'Free' },
		})}</script>`;
	globalThis.fetch = async () => ({ ok: true, json: async () => ({ gift_box: { items_subtotal: 100, packaging_amount: 0, total: 100, warnings: [] } }) });

	await import(`../../resources/js/gift-builder.js?mounted=${Date.now()}`);
	const card = document.querySelector('[data-gift-product-card]');
	const slot = document.querySelector('[data-gift-slot="main"]');
	document.querySelector('[data-select-main]').click();

	assert.equal(document.querySelector('[data-gift-product-card]'), card);
	assert.equal(document.querySelector('[data-gift-slot="main"]'), slot);
	assert.equal(slot.dataset.variantId, '11');
	assert.equal(slot.querySelector('img').getAttribute('src'), '/watch-blue.jpg');
	const summaryLine = document.querySelector('[data-gift-summary-line]');
	assert.equal(summaryLine.dataset.summaryKey, 'main-1');

	document.querySelector('[data-select-variant="12"]').click();
	assert.equal(document.querySelector('[data-gift-summary-line]'), summaryLine);
	assert.equal(slot.dataset.variantId, '12');
	assert.equal(slot.querySelector('img').getAttribute('src'), '/watch-pink.jpg');

	await new Promise((resolve) => setTimeout(resolve, 260));
	globalThis.fetch = originalFetch;
});
