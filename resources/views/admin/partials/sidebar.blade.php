<nav class="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('admin.home') }}" class="sidebar-brand">
            KidSIM<span>Admin</span>
        </a>
        <div class="sidebar-toggler not-active">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
    <div class="sidebar-body">
        <ul class="nav">
            <li class="nav-item nav-category">Main</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.home')])>
                <a href="{{ route('admin.home') }}" class="nav-link">
                    <i class="link-icon" data-feather="box"></i>
                    <span class="link-title">Dashboard</span>
                </a>
            </li>
            <li class="nav-item nav-category">Management</li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.products.*')])>
                <a href="{{ route('admin.products.index') }}" class="nav-link">
                    <i class="link-icon" data-feather="tag"></i>
                    <span class="link-title">Products</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.orders.*')])>
                <a href="{{ route('admin.orders.index') }}" class="nav-link">
                    <i class="link-icon" data-feather="shopping-cart"></i>
                    <span class="link-title">Orders</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.inquiries.*')])>
                <a href="{{ route('admin.inquiries.index') }}" class="nav-link">
                    <i class="link-icon" data-feather="message-circle"></i>
                    <span class="link-title">Inquiries</span>
                </a>
            </li>
            <li @class(['nav-item', 'active' => request()->routeIs('admin.users.*')])>
                <a href="{{ route('admin.users.index') }}" class="nav-link">
                    <i class="link-icon" data-feather="users"></i>
                    <span class="link-title">Users</span>
                </a>
            </li>
        </ul>
    </div>
</nav>
