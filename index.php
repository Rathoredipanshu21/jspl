
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>JSPL - Admin Panel</title>
    
    <!-- Google Fonts for a clean, modern look -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Favicon for the site -->
    <link rel="icon" type="image/png" href="Assets/logo.jpg" onerror="this.onerror=null;this.href='https://placehold.co/32x32/2c3e50/ffffff?text=A';" />
    
    <style>
        :root {
            --primary-color: #3498db;
            --sidebar-bg: #2c3a47;
            --sidebar-header-bg: #1e272e;
            --sidebar-text: #bdc3c7;
            --sidebar-hover-bg: #34495e;
            --sidebar-active-bg: #2980b9;
            --main-bg: #eef2f5;
            --navbar-bg: #ffffff;
            --text-color: #333;
            --shadow-color: rgba(0,0,0,0.08);
        }

        /* Basic Reset */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--main-bg);
            overflow: hidden; /* Prevent scrolling on the main body */
            color: var(--text-color);
        }
        .container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        /* Navbar Styling */
        .navbar {
            background: var(--navbar-bg);
            padding: 10px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px var(--shadow-color);
            flex-shrink: 0;
            z-index: 10;
        }
        .navbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .navbar-icons {
            display: flex;
            align-items: center;
            gap: 25px;
            font-size: 20px;
        }
        .navbar-icons i {
            cursor: pointer;
            transition: color 0.3s ease;
            color: #555;
        }
        .navbar-icons i:hover {
            color: var(--primary-color);
        }
        
        /* Main Content Area */
        .main {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease;
            box-shadow: 3px 0 15px rgba(0,0,0,0.1);
            flex-shrink: 0;
            overflow-y: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .sidebar::-webkit-scrollbar { display: none; }
        
        .sidebar.collapsed {
            width: 80px;
        }
        
        .sidebar-header {
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            padding: 25px;
            background: var(--sidebar-header-bg);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #fff;
        }
        .sidebar.collapsed .sidebar-header {
            font-size: 0;
            padding: 22px 0;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 10px 0;
            flex-grow: 1;
        }
        .sidebar-menu li {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            cursor: pointer;
            transition: background 0.3s, color 0.3s;
            color: var(--sidebar-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            position: relative;
        }
        .sidebar.collapsed .sidebar-menu li {
            padding: 15px 25px;
            justify-content: center;
        }
        .sidebar-menu li:hover {
            background: var(--sidebar-hover-bg);
            color: #ffffff;
        }
        .sidebar-menu li.active {
            background: var(--sidebar-active-bg);
            color: #ffffff;
        }
        .sidebar-menu li.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background-color: var(--primary-color);
        }
        .sidebar.collapsed .sidebar-menu li.active::before {
            width: 0;
        }
        .sidebar-menu i {
            width: 25px;
            text-align: center;
            font-size: 18px;
            transition: color 0.3s ease;
        }
        .sidebar-menu li:hover i, .sidebar-menu li.active i {
            color: var(--primary-color);
        }
        .sidebar.collapsed .sidebar-menu li span {
            display: none;
        }
        
        /* Logout Link Styling */
        .logout-container {
            padding: 20px;
            margin-top: auto;
        }
        .logout-link {
            background-color: #e74c3c;
            color: #fff;
            padding: 12px 16px;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.3s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            white-space: nowrap;
            overflow: hidden;
        }
        .logout-link:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        .sidebar.collapsed .logout-link span {
            display: none;
        }
        .sidebar.collapsed .logout-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            padding: 0;
        }

        /* IFrame Content Area */
        .content {
            flex: 1;
            background: var(--main-bg);
            overflow-y: auto;
            padding: 20px;
        }
        .content iframe {
            border: none;
            width: 100%;
            height: 100%;
            display: block;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px var(--shadow-color);
        }
        
        /* Toggle Button */
        .toggle-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #333;
            transition: color 0.3s ease, transform 0.3s ease;
        }
        .toggle-btn:hover { 
            color: var(--primary-color); 
            transform: rotate(90deg);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navbar">
            <div class="navbar-left">
                <button class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <!-- <span class="font-semibold">Welcome, <?php echo $username; ?>!</span> -->
            </div>
            <div class="navbar-icons">
                <i class="fas fa-plus-circle" title="Quick Add" onclick="loadPage(document.querySelector('#newOrderMenuItem'), 'new_orders.php')"></i>
                <i class="fas fa-bell"></i>
                <i class="fas fa-envelope"></i>
                <i class="fas fa-user-circle"></i>
                <i id="fullscreen-btn" class="fas fa-expand" onclick="toggleFullScreen()" title="Toggle Fullscreen"></i>
            </div>
        </div>

        <div class="main">
            <div class="sidebar" id="sidebar">
                <div class="sidebar-header">JSPL Trading</div>
                <ul class="sidebar-menu" id="sidebarMenu">
                    <li class="active" onclick="loadPage(this, 'Bill_create.php')">
                        <i class="fas fa-file-invoice-dollar"></i> <span>Billing</span>
                    </li>
                    <li onclick="loadPage(this, 'add_party.php')">
                        <i class="fas fa-users"></i> <span>Parties</span>
                    </li>
                    <li onclick="loadPage(this, 'add_goods.php')">
                        <i class="fas fa-boxes-stacked"></i> <span>Stock Management</span>
                    </li>
                    <li onclick="loadPage(this, 'invoices.php')">
                        <i class="fas fa-receipt"></i> <span>Invoices</span>
                    </li>
                    <li id="newOrderMenuItem" onclick="loadPage(this, 'new_orders.php')">
                        <i class="fas fa-truck-fast"></i> <span>New Orders</span>
                    </li>
                     <li onclick="loadPage(this, 'admin_approval.php')">
                        <i class="fas fa-user-check"></i> <span>Approvals</span>
                    </li>
                     <li onclick="loadPage(this, 'party_report.php')">
                        <i class="fas fa-user-check"></i> <span>Party report</span>
                    </li>
                </ul>
                <div class="logout-container">
                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                    </a>
                </div>
            </div>
            <div class="content">
                <iframe id="contentFrame" src="Bill_create.php" title="Admin Content"></iframe>
            </div>
        </div>
    </div>
    <script>
        /**
         * Toggles the 'collapsed' class on the sidebar element to expand/collapse it.
         */
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        }

        /**
         * Loads a new page into the iframe and updates the active state of the sidebar menu.
         * @param {HTMLElement} el - The list item element that was clicked.
         * @param {string} page - The URL of the page to load into the iframe.
         */
        function loadPage(el, page) {
            // Set the source of the iframe to the new page
            document.getElementById('contentFrame').src = page;
            
            // Remove 'active' class from all menu items
            const menuItems = document.querySelectorAll('#sidebarMenu li');
            menuItems.forEach(item => item.classList.remove('active'));
            
            // Add 'active' class to the clicked menu item
            if(el) {
                el.classList.add('active');
            }
        }

        /**
         * Toggles the entire page view between fullscreen and normal mode.
         */
        function toggleFullScreen() {
            const btn = document.getElementById('fullscreen-btn');
            if (!document.fullscreenElement && !document.mozFullScreenElement && !document.webkitFullscreenElement && !document.msFullscreenElement) {
                // Enter fullscreen mode
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.msRequestFullscreen) {
                    document.documentElement.msRequestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) {
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen(Element.ALLOW_KEYBOARD_INPUT);
                }
                btn.classList.remove('fa-expand');
                btn.classList.add('fa-compress');
            } else {
                // Exit fullscreen mode
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
                btn.classList.remove('fa-compress');
                btn.classList.add('fa-expand');
            }
        }
    </script>
</body>
</html>
