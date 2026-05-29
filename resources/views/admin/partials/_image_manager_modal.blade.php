{{-- Image Manager Modal --}}
<div class="modal fade" id="image-manager-modal" tabindex="-1" aria-labelledby="imageManagerModalLabel" inert>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageManagerModalLabel">Image Manager</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="imageManagerTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="gallery-tab" data-bs-toggle="tab" data-bs-target="#gallery-tab-pane" type="button" role="tab" aria-controls="gallery-tab-pane" aria-selected="true">Global Gallery</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="upload-tab" data-bs-toggle="tab" data-bs-target="#upload-tab-pane" type="button" role="tab" aria-controls="upload-tab-pane" aria-selected="false">Upload & Crop</button>
                    </li>
                </ul>
                <div class="tab-content" id="imageManagerTabContent">
                    {{-- Gallery Tab --}}
                    <div class="tab-pane fade show active" id="gallery-tab-pane" role="tabpanel" aria-labelledby="gallery-tab" tabindex="0">
                        <div class="row mb-3 g-2">
                            <div class="col-md-4">
                                <select class="form-select form-select-sm" id="gallery-filter-product">
                                    <option value="">All Products</option>
                                    @php
                                        // Load products if not already passed to view
                                        $galleryProducts = $products ?? \App\Models\Product::orderBy('name_en')->get(['id', 'name_en', 'name_ka']);
                                    @endphp
                                    @foreach($galleryProducts as $prod)
                                        <option value="{{ $prod->id }}">{{ $prod->name_en ?: $prod->name_ka }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" id="gallery-filter-time">
                                    <option value="">All Time</option>
                                    <option value="today">Today</option>
                                    <option value="week">Past Week</option>
                                    <option value="month">Past Month</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="btn-gallery-refresh">
                                    <i data-feather="refresh-cw" style="width:12px;height:12px;"></i> Filter
                                </button>
                            </div>
                        </div>

                        <div id="gallery-loading" class="text-center py-4 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div id="gallery-empty" class="text-center text-muted py-4 d-none">
                            No images found with the current filters.
                        </div>
                        <div id="gallery-grid" class="row g-2" style="max-height: 500px; overflow-y: auto; overflow-x: hidden;">
                            <!-- Images injected via JS -->
                        </div>
                        <div class="text-center mt-3 d-none" id="gallery-load-more-container">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-gallery-load-more">Load More</button>
                        </div>
                    </div>
                    
                    {{-- Upload & Crop Tab --}}
                    <div class="tab-pane fade" id="upload-tab-pane" role="tabpanel" aria-labelledby="upload-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-8">
                                <div id="upload-zone" class="border border-2 border-dashed rounded p-4 text-center cursor-pointer mb-3 bg-light">
                                    <i data-feather="upload-cloud" style="width:32px;height:32px;" class="text-muted mb-2"></i>
                                    <p class="mb-0">Drag and drop an image here or click to browse</p>
                                    <p class="small text-muted">Supports JPG, PNG, WEBP up to 5MB</p>
                                    <input type="file" id="standalone-image-upload" class="d-none" accept="image/jpeg,image/png,image/webp">
                                </div>
                                <div id="cropper-container" class="d-none mb-3" style="max-height: 400px;">
                                    <img id="cropper-image" src="" style="max-width: 100%;">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div id="cropper-controls" class="d-none">
                                    <h6 class="mb-3">Editing Tools</h6>
                                    
                                    <div class="mb-3">
                                        <label class="form-label small">Aspect Ratio</label>
                                        <select class="form-select form-select-sm" id="cropper-ratio">
                                            <option value="NaN">Free</option>
                                            <option value="1">1:1 (Instagram)</option>
                                            <option value="1.91">1.91:1 (Facebook Post)</option>
                                            <option value="1.33333">4:3</option>
                                            <option value="1.77777">16:9</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label small d-block">Rotate</label>
                                        <div class="btn-group btn-group-sm w-100">
                                            <button type="button" class="btn btn-outline-secondary" id="btn-crop-rotate-left" title="Rotate Left">
                                                <i data-feather="rotate-ccw" style="width:14px;height:14px;"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="btn-crop-rotate-right" title="Rotate Right">
                                                <i data-feather="rotate-cw" style="width:14px;height:14px;"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small d-block">Flip</label>
                                        <div class="btn-group btn-group-sm w-100">
                                            <button type="button" class="btn btn-outline-secondary" id="btn-crop-flip-h" title="Flip Horizontal">
                                                <i data-feather="code" style="width:14px;height:14px;transform: rotate(90deg);"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="btn-crop-flip-v" title="Flip Vertical">
                                                <i data-feather="code" style="width:14px;height:14px;"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <hr>
                                    
                                    <button type="button" class="btn btn-primary w-100 mb-2" id="btn-crop-save">
                                        <i data-feather="check" style="width:14px;height:14px;"></i> Crop & Upload
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary w-100" id="btn-crop-cancel">
                                        Cancel
                                    </button>
                                    
                                    <div class="progress mt-3 d-none" id="upload-progress-container" style="height: 10px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="upload-progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="btn-select-gallery-image" disabled>Use Selected Image</button>
            </div>
        </div>
    </div>
</div>