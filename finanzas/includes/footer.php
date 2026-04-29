        </main>
    </div>
</div>
<?php $chartsVersion = file_exists(__DIR__ . '/../assets/charts.js') ? (string) filemtime(__DIR__ . '/../assets/charts.js') : '1'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/charts.js?v=<?php echo htmlspecialchars($chartsVersion, ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
