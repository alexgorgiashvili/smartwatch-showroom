<nav class="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
            MyTechnic<span>Admin</span>
        </a>
        <div class="sidebar-toggler not-active">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    <div class="sidebar-body">
        <ul class="nav">

            {{-- ── Main ── --}}
            <li class="nav-item nav-category">Main</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.dashboard')])>
                <a href="{{ route('admin.dashboard') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="home"></i>
                    <span class="link-title">Dashboard</span>
                </a>
            </li>

            {{-- ── Commerce ── --}}
            <li class="nav-item nav-category">Commerce</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.orders.*')])>
                <a href="{{ route('admin.orders.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="shopping-cart"></i>
                    <span class="link-title">Orders</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.payments.*')])>
                <a href="{{ route('admin.payments.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="credit-card"></i>
                    <span class="link-title">Payments</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.inquiries.*')])>
                <a href="{{ route('admin.inquiries.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="mail"></i>
                    <span class="link-title">Inquiries</span>
                </a>
            </li>

            {{-- ── Catalog ── --}}
            <li class="nav-item nav-category">Catalog</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.products.*')])>
                <a href="{{ route('admin.products.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="tag"></i>
                    <span class="link-title">Products</span>
                </a>
            </li>

            {{-- ── Messaging ── --}}
            <li class="nav-item nav-category">Messaging</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.inbox.*')])>
                <a href="{{ route('admin.inbox.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="message-circle"></i>
                    <span class="link-title">Inbox</span>
                    @php $unreadCount = \App\Models\Conversation::where('unread_count', '>', 0)->sum('unread_count'); @endphp
                    <span id="sidebar-inbox-badge"
                          class="badge bg-danger badge-pill ms-2 {{ $unreadCount > 0 ? '' : 'd-none' }}"
                          data-unread-count="{{ $unreadCount }}">
                        {{ $unreadCount }}
                    </span>
                </a>
            </li>

            {{-- ── Content ── --}}
            <li class="nav-item nav-category">Content</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.articles.*')])>
                <a href="{{ route('admin.articles.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="file-text"></i>
                    <span class="link-title">Blog Articles</span>
                </a>
            </li>
            {{-- ── AI Lab ── --}}
            <li class="nav-item nav-category">AI Lab</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.ai-analytics')])>
                <a href="{{ route('admin.ai-analytics') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="bar-chart-2"></i>
                    <span class="link-title">AI Analytics</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.product-quality.*')])>
                <a href="{{ route('admin.product-quality.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="shield"></i>
                    <span class="link-title">Product Quality</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.chatbot-content.*')])>
                <a href="{{ route('admin.chatbot-content.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="cpu"></i>
                    <span class="link-title">Chatbot Content</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.chatbot-lab.*')])>
                <a href="{{ route('admin.chatbot-lab.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="terminal"></i>
                    <span class="link-title">Chatbot Lab</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.chatbot-testing')])>
                <a href="{{ route('admin.chatbot-testing') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="message-square"></i>
                    <span class="link-title">Chatbot Testing</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.chatbot-training*')])>
                <a href="{{ route('admin.chatbot-training') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="layers"></i>
                    <span class="link-title">Chatbot Training</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.chatbot-traces')])>
                <a href="{{ route('admin.chatbot-traces') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="list"></i>
                    <span class="link-title">Chatbot Traces</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.langfuse-dashboard')])>
                <a href="{{ route('admin.langfuse-dashboard') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="activity"></i>
                    <span class="link-title">Langfuse Dashboard</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.langfuse-link')])>
                <a href="{{ route('admin.langfuse-link') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="settings"></i>
                    <span class="link-title">Langfuse Setup</span>
                </a>
            </li>

            {{-- ── Social ── --}}
            <li class="nav-item nav-category">Social</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.social-dashboard')])>
                <a href="{{ route('admin.social-dashboard') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="share-2"></i>
                    <span class="link-title">Social Dashboard</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.social-comments.*')])>
                <a href="{{ route('admin.social-comments.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="message-square"></i>
                    <span class="link-title">Social Comments</span>
                </a>
            </li>

            {{-- ── Competition ── --}}
            <li class="nav-item nav-category">Competition</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.competitors.*')])>
                <a href="{{ route('admin.competitors.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="activity"></i>
                    <span class="link-title">Competitor Monitor</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.fb-competitors')])>
                <a href="{{ route('admin.fb-competitors') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="eye"></i>
                    <span class="link-title">FB Competitors</span>
                </a>
            </li>

            {{-- ── SEO ── --}}
            <li class="nav-item nav-category">SEO</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.seo')])>
                <a href="{{ route('admin.seo') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="search"></i>
                    <span class="link-title">SEO Monitoring</span>
                </a>
            </li>

            {{-- ── Admin ── --}}
            <li class="nav-item nav-category">Admin</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.users.*')])>
                <a href="{{ route('admin.users.index') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="users"></i>
                    <span class="link-title">Users</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.sms')])>
                <a href="{{ route('admin.sms') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="smartphone"></i>
                    <span class="link-title">SMS Activation</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.alibaba-import')])>
                <a href="{{ route('admin.alibaba-import') }}" class="nav-link" data-pjax>
                    <i class="link-icon" data-feather="download-cloud"></i>
                    <span class="link-title">Import Alibaba</span>
                </a>
            </li>

        </ul>
    </div>
</nav>
