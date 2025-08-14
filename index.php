<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Educare - Admin Panel</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="icon" type="image/png" href="Assets\logo.png" />
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #fdfbf5;
            overflow: hidden;
        }
        .container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .navbar {
            background: #2c3e50;
            padding: 10px 20px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }
        .navbar-icons {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 20px;
        }
        .navbar-icons i {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .navbar-icons i:hover {
            color: #1abc9c;
        }
        .main {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        .sidebar {
            width: 260px;
            background: #1f2d3d;
            color: #fff;
            display: flex;
            flex-direction: column;
            transition: width 0.3s ease, min-width 0.3s ease;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            flex-shrink: 0;
            overflow-y: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .sidebar::-webkit-scrollbar { display: none; }
        .sidebar.collapsed {
            width: 70px;
            min-width: 70px;
        }
        .sidebar-header {
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            padding: 40px;
            background: #17212b;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar.collapsed .sidebar-header {
            font-size: 0;
            padding: 20px 0;
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
            gap: 15px;
            cursor: pointer;
            transition: background 0.3s, color 0.3s;
            color: #eaeaea;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            position: relative;
        }
        .sidebar.collapsed .sidebar-menu li {
            padding: 15px 20px;
            justify-content: center;
        }
        .sidebar-menu li:hover:not(.active) {
            background: #34495e;
            color: #fff;
        }
        .sidebar-menu li.active {
            background-color: #34495e;
            color: #ffffff;
            border-left: 4px solid #1abc9c;
            padding-left: 21px;
        }
        .sidebar.collapsed .sidebar-menu li.active {
            padding-left: 20px;
            border-left: none;
        }
        .sidebar-menu i {
            width: 25px;
            text-align: center;
            font-size: 16px;
            color: #95a5a6;
            transition: color 0.3s ease;
        }
        .sidebar-menu li:hover i,
        .sidebar-menu li.active i {
            color: #1abc9c;
        }
        .sidebar.collapsed .sidebar-menu li span,
        .sidebar.collapsed .dropdown-content {
            display: none;
        }
        .dropdown {
            flex-direction: column;
        }
        .dropdown > div {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }
        .dropdown .dropdown-content {
            display: none;
            flex-direction: column;
            padding-left: 45px;
            background-color: #2d3e4f;
            border-top: 1px solid rgba(255,255,255,0.03);
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out, padding-top 0.3s ease-out, padding-bottom 0.3s ease-out;
        }
        .dropdown.open .dropdown-content {
            display: flex;
            max-height: 500px;
            padding-top: 5px;
            padding-bottom: 5px;
        }
        .dropdown-content li {
            padding: 10px 0;
            font-size: 14px;
            color: #eee;
            cursor: pointer;
            gap: 10px;
            border-left: 1px dotted rgba(255,255,255,0.3);
            padding-left: 15px;
        }
        .sidebar.collapsed .dropdown-content li {
            padding-left: 10px;
            border-left: none;
        }
        .dropdown-content li:hover {
            color: #1abc9c;
        }
        .dropdown-content li.active {
            background-color: #3f5870;
            color: #1abc9c;
        }
        .dropdown .fa-angle-down {
            transition: transform 0.3s ease;
        }
        .dropdown.open .fa-angle-down {
            transform: rotate(180deg);
        }
        .content {
            flex: 1;
            background: #fdfbf5;
            overflow-y: auto;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .content::-webkit-scrollbar { display: none; }
        .content iframe {
            border: none;
            width: 100%;
            height: 100%;
        }
        .toggle-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #fff;
            transition: color 0.3s ease;
        }
        .toggle-btn:hover { color: #1abc9c; }
        .sidebar-menu a {
            background-color: #e74c3c;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            margin: 10px auto;
            font-size: 15px;
            text-decoration: none;
            transition: background 0.3s ease, transform 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 80%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-menu a:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        .sidebar.collapsed .sidebar-menu a {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            padding: 0;
            justify-content: center;
            align-items: center;
        }
        .sidebar.collapsed .sidebar-menu a span {
            display: none;
        }
        .sidebar-menu a i {
            margin-right: 8px;
        }
        .sidebar.collapsed .sidebar-menu a i {
            margin-right: 0;
            font-size: 20px;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
    <div class="navbar">
        <button class="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <div class="navbar-icons">
            <i class="fas fa-bell"></i>
            <i class="fas fa-envelope"></i>
            <i class="fas fa-user-circle"></i>
        </div>
    </div>

    <div class="main">
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">Admin Panel</div>
            <ul class="sidebar-menu" id="sidebarMenu">
                <li onclick="loadPage(this, 'dashboard.php')"><i class="fas fa-home"></i> <span>Dashboard</span></li>

                <!-- <li class="dropdown">
                    <div onclick="toggleDropdown(this)">
                        <i class="fas fa-user-graduate"></i> <span>Student Management</span> <i class="fas fa-angle-down"></i>
                    </div>
                    <ul class="dropdown-content">
                        <li onclick="loadPage(this, '../Center/admission_form.php')"><i class="fas fa-file-invoice"></i> <span>Take Admission</span></li>
                        <li onclick="loadPage(this, 'All_students.php')"><i class="fas fa-users-viewfinder"></i> <span>View All Students</span></li> 
                    </ul>
                </li>

                <li class="dropdown">
                    <div onclick="toggleDropdown(this)">
                        <i class="fas fa-university"></i> <span>Universities Details</span> <i class="fas fa-angle-down"></i>
                    </div>
                    <ul class="dropdown-content">
                        <li onclick="loadPage(this, 'university_form.php')"><i class="fas fa-plus-circle"></i> <span>Add Universities</span></li>
                        <li onclick="loadPage(this, 'view_universities.php')"><i class="fas fa-list-alt"></i> <span>View All Universities</span></li>
                        <li onclick="loadPage(this, 'ClgFee/index.php')"><i class="fas fa-handshake"></i> <span>Payment to college</span></li>
                        <li onclick="loadPage(this, 'ClgFee/fee_management.php')"><i class="fas fa-handshake"></i> <span>College Payment Details</span></li>
                    </ul>
                </li>
            
                <li class="dropdown">
                    <div onclick="toggleDropdown(this)">
                        <i class="fas fa-dollar-sign"></i> <span>Fee Management</span>
                        <i class="fas fa-angle-down"></i>
                    </div>
                   <ul class="dropdown-content">
                        <li onclick="loadPage(this, 'reg_fee_controller.php')"><i class="fas fa-cash-register"></i> <span>Registration Fee Settings</span></li>
                        <li onclick="loadPage(this, 'admin_fee_control.php')"><i class="fas fa-file-invoice"></i> <span>Admission & Late Fee Configuration</span></li>
                        <li onclick="loadPage(this, 'fee_management.php')"><i class="fas fa-graduation-cap"></i> <span>Course Fee Management</span></li>
</ul>

                </li>
                <li class="dropdown">
                    <div onclick="toggleDropdown(this)">
                        <i class="fas fa-dollar-sign"></i> <span>Commission Dashboard</span>
                        <i class="fas fa-angle-down"></i>
                    </div>
                   <ul class="dropdown-content">
                        <li   li onclick="loadPage(this, 'Commission/admin_commission_dashboard.php')"><i class="fas fa-cash-register"></i> <span>Commission Dashboard</span></li>
                
</ul>

                </li>

                <li class="dropdown">
                    <div onclick="toggleDropdown(this)">
                        <i class="fas fa-dollar-sign"></i> <span>Franchise Management</span>
                        <i class="fas fa-angle-down"></i>
                    </div>
                    <ul class="dropdown-content">
                        <li onclick="loadPage(this, 'franchise_requests.php')"><i class="fas fa-handshake"></i> <span>Franchise Requests</span></li>
                        <li onclick="loadPage(this, 'Center/create_franchise_login_form.php')"><i class="fas fa-handshake"></i> <span>Create Franchise Login </span></li>
                      

                    </ul>
                </li> -->

                <li onclick="loadPage(this, 'Bill_create.php')"><i class="fas fa-map-marked-alt"></i> <span>Create Bill</span></li>
                <li onclick="loadPage(this, 'add_party.php')"><i class="fas fa-tasks"></i> <span>Add party</span></li>
                <li onclick="loadPage(this, 'add_goods.php')"><i class="fas fa-file-alt"></i> <span>Add Goods</span></li>
                <li onclick="loadPage(this, 'payment_tracking.php')"><i class="fas fa-credit-card"></i> <span>Payment Tracking</span></li>
                <li onclick="loadPage(this, 'counseling.php')"><i class="fas fa-calendar-check"></i> <span>Counseling Schedule</span></li>
                <li onclick="loadPage(this, 'announcements.php')"><i class="fas fa-bullhorn"></i> <span>Announcements</span></li>
                <li onclick="loadPage(this, 'reports.php')"><i class="fas fa-chart-pie"></i> <span>Reports</span></li>
                <li onclick="loadPage(this, 'staff_management.php')"><i class="fas fa-user-tie"></i> <span>Staff Management</span></li>
                <li onclick="loadPage(this, 'settings.php')"><i class="fas fa-cogs"></i> <span>Settings</span></li>
                <li>
                    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
                </li>
            </ul>
        </div>
        <div class="content">
            <iframe id="contentFrame" src="Dashboard.php"></iframe>
        </div>
    </div>
</div>
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('collapsed');
    }
    function toggleDropdown(element) {
        const parentLi = element.closest('.dropdown');
        if (parentLi) {
            parentLi.classList.toggle('open');
        }
    }
    function loadPage(el, page) {
        document.getElementById('contentFrame').src = page;
        const menuItems = document.querySelectorAll('#sidebarMenu li');
        menuItems.forEach(item => item.classList.remove('active'));
        el.classList.add('active');
    }
</script>
</body>
</html>
