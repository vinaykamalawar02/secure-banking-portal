</main>
    </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

<script>
    $(document).ready(function() {
        // Enable DataTables
        $('table').DataTable({
            responsive: true
        });
        
        // Confirm before delete
        $('.delete-btn').click(function() {
            return confirm('Are you sure you want to delete this record?');
        });
    });
</script>
</body>
</html>