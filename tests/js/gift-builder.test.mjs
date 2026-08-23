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
