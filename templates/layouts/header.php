<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
    <?php if (isset($extraCss)): ?>
        <?= $extraCss ?>
    <?php endif; ?>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-bofa-navy fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>">
                <i class="fas fa-shield-halved text-bofa-red me-2"></i>
                <span class="fw-bold"><?= APP_NAME ?></span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarTop">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarTop">
                <!-- Search -->
                <?php if (Auth::isAgent() || Auth::isAdmin()): ?>
                <form class="d-flex mx-auto" style="max-width:400px" action="<?= BASE_URL ?>api/search" method="GET">
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control bg-white bg-opacity-10 border-0 text-white"
                               name="q" placeholder="Rechercher (ID, nom, email...)">
                        <button class="btn btn-outline-light" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Notifications -->
                    <li class="nav-item dropdown me-2">
                        <a class="nav-link position-relative" href="#" id="notifDropdown" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <span class="badge bg-danger rounded-pill position-absolute top-0 start-100 translate-middle notif-count" style="display:none">0</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-end shadow notif-menu" style="width:350px;max-height:400px;overflow-y:auto">
                            <h6 class="dropdown-header">Notifications</h6>
                            <div id="notif-list">
                                <div class="text-center text-muted p-3"><small>Aucune notification</small></div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item text-center small" id="mark-all-read">
                                Tout marquer comme lu
                            </a>
                        </div>
                    </li>

                    <!-- User Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                            <div class="avatar-sm me-2">
                                <i class="fas fa-user-circle fa-lg"></i>
                            </div>
                            <span class="d-none d-md-inline"><?= htmlspecialchars(Auth::name()) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li><span class="dropdown-item-text text-muted small"><?= htmlspecialchars(Session::get('user_email')) ?></span></li>
                            <li><span class="dropdown-item-text"><span class="badge bg-primary"><?= ucfirst(Auth::role()) ?></span></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <?php if (Auth::isClient()): ?>
                                <li><a class="dropdown-item" href="<?= BASE_URL ?>client/profile"><i class="fas fa-user me-2"></i>Mon profil</a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout"><i class="fas fa-sign-out-alt me-2"></i>Déconnexion</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Layout -->
    <div class="d-flex" id="wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Page Content -->
        <div id="page-content" class="flex-grow-1">
            <div class="container-fluid p-4">
                <!-- Flash Messages -->
                <?php if (Session::hasFlash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-1"></i> <?= Session::getFlash('success') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (Session::hasFlash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-1"></i> <?= Session::getFlash('error') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (Session::hasFlash('warning')): ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-1"></i> <?= Session::getFlash('warning') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
