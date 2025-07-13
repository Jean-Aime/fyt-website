<footer class="admin-footer">
    <div class="footer-content">
        <p>&copy; <?php echo date('Y'); ?> Forever Young Tours. All rights reserved.</p>
        <p>
            Powered by <a href="https://www.example.com" target="_blank">Example Company</a>
        </p>
    </div>
</footer>

<style>
.admin-footer {
    background: #f8f9fa;
    border-top: 1px solid #e3e6f0;
    padding: 20px;
    text-align: center;
    font-size: 0.8em;
    color: #666;
}

.footer-content {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.footer-content a {
    color: var(--admin-primary);
    text-decoration: none;
}

.footer-content a:hover {
    text-decoration: underline;
}
</style>
