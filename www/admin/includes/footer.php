        </div>
    </main>
    
    <script>
        // 共通JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // フラッシュメッセージの自動非表示
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>
