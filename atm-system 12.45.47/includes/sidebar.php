<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" 
                           href="<?= BASE_URL ?>/<?= $_SESSION['user_role'] ?>/index.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                    </li>
                    
                    <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'managers.php' ? 'active' : '' ?>" 
                               href="<?= BASE_URL ?>/admin/managers.php">
                                <i class="fas fa-user-tie me-2"></i>
                                Managers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : '' ?>" 
                               href="<?= BASE_URL ?>/admin/customers.php">
                                <i class="fas fa-users me-2"></i>
                                Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'requests.php' ? 'active' : '' ?>" 
                               href="<?= BASE_URL ?>/admin/requests.php">
                                <i class="fas fa-clipboard-list me-2"></i>
                                Account Requests
                            </a>
                        </li>
                    <?php elseif ($_SESSION['user_role'] === 'manager'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'add_customer.php' ? 'active' : '' ?>" 
                               href="<?= BASE_URL ?>/manager/add_customer.php">
                                <i class="fas fa-user-plus me-2"></i>
                                Add Customer
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'transactions.php' ? 'active' : '' ?>" 
                               href="<?= BASE_URL ?>/manager/transactions.php">
                                <i class="fas fa-exchange-alt me-2"></i>
                                Transactions
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'withdraw.php' ? 'active' : '' ?>" 
                               href="<?= BASE_URL ?>/user/withdraw.php">
                                <i class="fas fa-money-bill-wave me-2"></i>
                                Withdraw Cash
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'transactions.php' ? 'active' : '' ?>" 
                               href="<?= BASE_URL ?>/user/transactions.php">
                                <i class="fas fa-history me-2"></i>
                                Transaction History
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>Account</span>
                </h6>
                <ul class="nav flex-column mb-2">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/profile.php">
                            <i class="fas fa-user-circle me-2"></i>
                            My Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Sign Out
                        </a>
                    </li>
                </ul>
            </div>
        </nav>