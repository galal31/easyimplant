    </main>

    <!-- Notification Toast -->
    <div id="toast" class="fixed bottom-5 right-5 transform translate-y-20 opacity-0 transition-all duration-300 bg-[#13324a] text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-3 z-50">
        <i class="fa-solid fa-circle-check text-emerald-400"></i>
        <span id="toastMsg" class="text-sm font-semibold"></span>
    </div>

    <script src="js/ajax-tables.js"></script>
    <script>
        function showToast(message) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = message;
            toast.classList.remove('translate-y-20', 'opacity-0');
            setTimeout(() => {
                toast.classList.add('translate-y-20', 'opacity-0');
            }, 3000);
        }

        async function deleteClinic(clinicId) {
            if(!confirm('Are you sure you want to permanently delete this clinic? All associated requests and data will be lost.')) return;

            try {
                const response = await fetch('../api/delete_clinic.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `clinic_id=${clinicId}`
                });
                
                const data = await response.json();
                if(response.ok) {
                    showToast(data.success);
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    alert(data.error);
                }
            } catch(e) {
                alert('Network error occurred.');
            }
        }

        async function handleClinic(clinicId, action, reloadOnSuccess = false) {
            if(!confirm(`Are you sure you want to ${action === 'paused' ? 'pause' : (action === 'approved' ? (reloadOnSuccess ? 'activate' : 'approve') : action)} this clinic?`)) return;

            try {
                const response = await fetch('../api/approve_clinic.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `clinic_id=${clinicId}&action=${action}`
                });
                
                const data = await response.json();
                if(response.ok) {
                    showToast(data.success);
                    if (reloadOnSuccess) {
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        const row = document.getElementById(`clinic-row-${clinicId}`);
                        if(row) row.remove();
                    }
                } else {
                    alert(data.error);
                }
            } catch(e) {
                alert('Network error occurred.');
            }
        }

        async function updateStatus(requestId, newStatus) {
            if(!newStatus) return;

            let reason = '';
            if (newStatus === 'rejected') {
                reason = prompt('Please write the rejection reason shown to the clinic:') || '';
                reason = reason.trim();
                if (!reason) {
                    alert('Rejection reason is required.');
                    window.location.reload();
                    return;
                }
            }
            
            try {
                const body = new URLSearchParams({request_id: requestId, status: newStatus, reason});
                const response = await fetch('../api/update_request_status.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: body.toString()
                });
                
                const data = await response.json();
                if(response.ok) {
                    showToast(data.success || 'Status updated successfully');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    alert(data.error || 'Could not update status.');
                }
            } catch(e) {
                alert('Network error occurred.');
            }
        }
    </script>
</body>
</html>
