@extends('admin.layout')

 @section('title', 'Langfuse — ადმინი')

 @section('content')
 @fragment('content')
 <div data-page-title="Langfuse">
     <div class="d-flex justify-content-between align-items-center flex-wrap grid-margin">
         <div>
             <h4 class="mb-3 mb-md-0">Langfuse</h4>
            <p class="text-muted mb-0">Chatbot observability dashboard trace-ებისთვის, token-ებისთვის, latency-სა და ხარჯისთვის.</p>
         </div>
         <div class="d-flex gap-2">
             <a href="{{ route('admin.langfuse-dashboard') }}" class="btn btn-outline-secondary btn-sm" data-pjax>
                გახსენი Dashboard
             </a>
             <a href="{{ $langfuseBaseUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">
                გახსენი Langfuse
             </a>
         </div>
     </div>

    <div class="row g-3">
         <div class="col-md-6">
             <div class="card h-100">
                 <div class="card-body">
                    <div class="text-muted small">სტატუსი</div>
                     @if($langfuseEnabled)
                        <div class="h5 mt-2 mb-3 text-success">ჩართულია</div>
                     @else
                        <div class="h5 mt-2 mb-3 text-danger">გამორთულია</div>
                     @endif

                    <div class="text-muted small">UI მისამართი</div>
                     <div class="font-monospace small mt-2">{{ $langfuseBaseUrl }}</div>
                    <div class="text-muted small mt-3">რეჟიმი</div>
                    <div class="small mt-2">Cloud ან self-hosted endpoint — რაც `LANGFUSE_BASE_URL`-ში გაქვს კონფიგურირებული.</div>
                 </div>
             </div>
         </div>
         <div class="col-md-6">
             <div class="card h-100">
                 <div class="card-body">
                    <div class="text-muted small">რა ჩანს Langfuse-ში</div>
                     <ul class="mt-3 mb-0 ps-3">
                        <li>ყოველი conversation-ის trace</li>
                        <li>intent და RAG child span-ები</li>
                        <li>model generation-ები token-ებით და latency-ით</li>
                        <li>conversation/customer/intent metadata</li>
                     </ul>
                    <div class="small text-muted mt-3">Docker საჭიროა მხოლოდ მაშინ, თუ optional self-hosted fallback-ის გაშვება გინდა.</div>
                 </div>
             </div>
         </div>
     </div>
</div>
@endfragment
@endsection
