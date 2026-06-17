@extends('admin.layout')

@section('title', 'Alibaba Import — Admin')

@section('content')
@fragment('content')
<div data-page-title="Alibaba Import">
    <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
        <div><h4 class="mb-3 mb-md-0">Alibaba Import</h4></div>
        <div>
            <a href="{{ route('admin.products.index') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                <i data-feather="arrow-left" style="width:14px;height:14px;"></i> Products
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="card-title mb-3">Import Source</h6>
            <ul class="nav nav-tabs mb-3" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-apify" role="tab">Apify JSON</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-raw-html" role="tab">Raw HTML</a></li>
            </ul>
            <div class="tab-content">
                <div class="tab-pane fade show active" id="tab-apify" role="tabpanel">
                    <div class="mb-3">
                        <label class="form-label">Apify JSON</label>
                        <textarea class="form-control" id="apifyJson" rows="6" placeholder='Paste Apify JSON output...'></textarea>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="btnParseApify">
                        <i data-feather="search" style="width:14px;height:14px;"></i> Parse
                    </button>
                </div>
                <div class="tab-pane fade" id="tab-raw-html" role="tabpanel">
                    <div class="mb-3">
                        <label class="form-label">Page Source / Raw HTML</label>
                        <textarea class="form-control" id="rawHtml" rows="8" placeholder="Paste full browser View Source / Inspect source HTML..."></textarea>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="btnParseRawHtml">
                        <i data-feather="search" style="width:14px;height:14px;"></i> Parse
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Parse Result (populated by JS) --}}
    <div class="card d-none" id="parseResultCard">
        <div class="card-body">
            <h6 class="card-title mb-3">Parsed Product</h6>
            <div id="parseResultContent"></div>
            <button type="button" class="btn btn-success btn-sm mt-3" id="btnConfirmImport">
                <i data-feather="check" style="width:14px;height:14px;"></i> Confirm Import
            </button>
        </div>
    </div>
</div>

@php
    $importAlibabaConfig = [
        'parseUrl' => route('admin.products.import-alibaba.parse'),
        'confirmUrl' => route('admin.products.import-alibaba.confirm'),
    ];
@endphp
<script id="import-urls" type="application/json">{!! json_encode($importAlibabaConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endfragment
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const urls = JSON.parse(document.getElementById('import-urls')?.textContent || '{}');
    const resultCard = document.getElementById('parseResultCard');
    const resultContent = document.getElementById('parseResultContent');
    let parsedData = null;

    function buildConfirmPayload(payload) {
        const product = payload?.product || {};

        return {
            ...product,
            source_url: payload?.source_url || null,
            source_product_id: payload?.source_product_id || null,
            selected_images: Array.isArray(payload?.images) ? payload.images : [],
            variants: Array.isArray(payload?.variants) ? payload.variants : [],
        };
    }

    async function doParse(data) {
        try {
            resultCard?.classList.add('d-none');
            const res = await axios.post(urls.parseUrl, data, { headers: { Accept: 'application/json' } });
            parsedData = res.data?.data || null;
            if (resultContent) {
                resultContent.innerHTML = `<pre class="bg-light p-3 rounded" style="max-height:400px;overflow:auto;">${JSON.stringify(parsedData, null, 2)}</pre>`;
            }
            resultCard?.classList.remove('d-none');
        } catch (e) {
            const msg = e.response?.data?.message || 'Parse failed';
            if (window.AdminHelpers) window.AdminHelpers.showToast(msg, 'error');
        }
    }

    document.getElementById('btnParseApify')?.addEventListener('click', () => {
        const val = document.getElementById('apifyJson')?.value?.trim();
        if (!val) return;
        doParse({ import_source: 'apify', apify_json: val });
    });

    document.getElementById('btnParseRawHtml')?.addEventListener('click', () => {
        doParse({
            import_source: 'raw_html',
            raw_html: document.getElementById('rawHtml')?.value?.trim() || '',
        });
    });

    document.getElementById('btnConfirmImport')?.addEventListener('click', async () => {
        if (!parsedData) return;
        try {
            const res = await axios.post(urls.confirmUrl, buildConfirmPayload(parsedData), { headers: { Accept: 'application/json' } });
            if (window.AdminHelpers) window.AdminHelpers.showToast(res.data.message || 'Imported!', 'success');
            if (res.data.redirect && window.AdminRouter) window.AdminRouter.navigate(res.data.redirect);
        } catch (e) {
            const msg = e.response?.data?.message || 'Import failed';
            if (window.AdminHelpers) window.AdminHelpers.showToast(msg, 'error');
        }
    });
});
</script>
@endpush
