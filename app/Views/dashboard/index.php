<section class="stat-grid">
    <?php foreach ($stats as $stat): ?>
        <article class="stat-card stat-<?= e($stat['tone']) ?>">
            <span class="stat-icon" aria-hidden="true"></span>
            <div>
                <p><?= e($stat['label']) ?></p>
                <strong><?= e($stat['value']) ?></strong>
                <small><?= e($stat['hint']) ?></small>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="dashboard-grid">
    <article class="panel panel-wide">
        <div class="panel-header">
            <h2>Activité du mois</h2>
            <button class="button button-light" type="button">Ce mois</button>
        </div>
        <div class="empty-chart">
            <span></span>
            <p>Les statistiques apparaîtront après l’ajout des patients, rendez-vous et paiements.</p>
        </div>
    </article>

    <article class="panel">
        <div class="panel-header">
            <h2>Modules prêts</h2>
        </div>
        <ul class="module-list">
            <li>Patients</li>
            <li>Agenda et rendez-vous</li>
            <li>Traitements</li>
            <li>Facturation en MGA</li>
            <li>Stock et documents</li>
        </ul>
    </article>

    <article class="panel">
        <div class="panel-header">
            <h2>Prochaine construction</h2>
        </div>
        <p class="panel-copy">La base est prête pour brancher les vues réelles sur les tables MySQL. Le module Patients est le meilleur prochain point de départ.</p>
    </article>
</section>
