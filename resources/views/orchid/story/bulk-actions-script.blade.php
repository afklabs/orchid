{{-- resources/views/orchid/story/bulk-actions-script.blade.php --}}

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select All functionality
    const selectAllCheckbox = document.getElementById('select-all-stories');
    const bulkCheckboxes = document.querySelectorAll('.bulk-checkbox input[type="checkbox"]');
    const bulkActionButtons = document.querySelectorAll('.bulk-action-btn');
    
    // Create Select All checkbox in table header
    const firstTh = document.querySelector('table thead tr th:first-child');
    if (firstTh && !document.getElementById('select-all-stories')) {
        firstTh.innerHTML = '<input type="checkbox" id="select-all-stories" class="form-check-input">';
    }
    
    // Update Select All checkbox after creation
    const selectAllCheckboxUpdated = document.getElementById('select-all-stories');
    
    if (selectAllCheckboxUpdated) {
        selectAllCheckboxUpdated.addEventListener('change', function() {
            const isChecked = this.checked;
            bulkCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateBulkActionButtons();
        });
    }
    
    // Individual checkbox change
    bulkCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectAllCheckbox();
            updateBulkActionButtons();
        });
    });
    
    // Update Select All checkbox based on individual checkboxes
    function updateSelectAllCheckbox() {
        const checkedCount = document.querySelectorAll('.bulk-checkbox input[type="checkbox"]:checked').length;
        const totalCount = bulkCheckboxes.length;
        
        if (selectAllCheckboxUpdated) {
            selectAllCheckboxUpdated.checked = checkedCount === totalCount;
            selectAllCheckboxUpdated.indeterminate = checkedCount > 0 && checkedCount < totalCount;
        }
    }
    
    // Update bulk action buttons state
    function updateBulkActionButtons() {
        const checkedCount = document.querySelectorAll('.bulk-checkbox input[type="checkbox"]:checked').length;
        const hasSelection = checkedCount > 0;
        
        // Update button text with count
        const bulkPublishBtn = document.querySelector('button[name="asyncMethod"][value="bulkPublish"]');
        const bulkUnpublishBtn = document.querySelector('button[name="asyncMethod"][value="bulkUnpublish"]');
        const bulkDeleteBtn = document.querySelector('button[name="asyncMethod"][value="bulkDelete"]');
        
        if (bulkPublishBtn) {
            bulkPublishBtn.textContent = hasSelection ? `Publish (${checkedCount})` : 'Bulk Publish';
            bulkPublishBtn.disabled = !hasSelection;
        }
        
        if (bulkUnpublishBtn) {
            bulkUnpublishBtn.textContent = hasSelection ? `Unpublish (${checkedCount})` : 'Bulk Unpublish';
            bulkUnpublishBtn.disabled = !hasSelection;
        }
        
        if (bulkDeleteBtn) {
            bulkDeleteBtn.textContent = hasSelection ? `Delete (${checkedCount})` : 'Bulk Delete';
            bulkDeleteBtn.disabled = !hasSelection;
        }
        
        // Update bulk action dropdown
        const bulkActionSelect = document.querySelector('select[name="bulk_action"]');
        const executeBulkBtn = document.querySelector('button[name="asyncMethod"][value="executeBulkAction"]');
        
        if (executeBulkBtn) {
            executeBulkBtn.disabled = !hasSelection;
            executeBulkBtn.textContent = hasSelection ? `Execute on (${checkedCount})` : 'Execute Bulk Action';
        }
    }
    
    // Handle bulk action form submission
    document.addEventListener('submit', function(e) {
        const form = e.target;
        const checkedBoxes = document.querySelectorAll('.bulk-checkbox input[type="checkbox"]:checked');
        
        if (checkedBoxes.length === 0) {
            const bulkButtons = ['bulkPublish', 'bulkUnpublish', 'bulkDelete', 'executeBulkAction'];
            const submitButton = form.querySelector('button[type="submit"]');
            
            if (submitButton && bulkButtons.includes(submitButton.value)) {
                e.preventDefault();
                alert('Please select at least one story.');
                return false;
            }
        }
        
        // Add selected story IDs to form
        if (checkedBoxes.length > 0) {
            checkedBoxes.forEach(checkbox => {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'bulk_stories[]';
                hiddenInput.value = checkbox.value;
                form.appendChild(hiddenInput);
            });
        }
    });
    
    // Initialize button states
    updateBulkActionButtons();
    
    // Bulk action select change handler
    const bulkActionSelect = document.querySelector('select[name="bulk_action"]');
    const bulkCategorySelect = document.querySelector('select[name="bulk_category_id"]');
    const bulkLevelSelect = document.querySelector('select[name="bulk_reading_level"]');
    
    if (bulkActionSelect) {
        bulkActionSelect.addEventListener('change', function() {
            const selectedAction = this.value;
            
            // Show/hide additional fields based on action
            if (bulkCategorySelect) {
                bulkCategorySelect.style.display = selectedAction === 'change_category' ? 'block' : 'none';
                bulkCategorySelect.required = selectedAction === 'change_category';
            }
            
            if (bulkLevelSelect) {
                bulkLevelSelect.style.display = selectedAction === 'change_level' ? 'block' : 'none';
                bulkLevelSelect.required = selectedAction === 'change_level';
            }
        });
    }
    
    // Add visual feedback for selected rows
    bulkCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('tr');
            if (this.checked) {
                row.classList.add('table-active');
            } else {
                row.classList.remove('table-active');
            }
        });
    });
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+A or Cmd+A to select all
        if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea')) {
            e.preventDefault();
            if (selectAllCheckboxUpdated) {
                selectAllCheckboxUpdated.checked = true;
                selectAllCheckboxUpdated.dispatchEvent(new Event('change'));
            }
        }
        
        // Escape to deselect all
        if (e.key === 'Escape') {
            if (selectAllCheckboxUpdated) {
                selectAllCheckboxUpdated.checked = false;
                selectAllCheckboxUpdated.dispatchEvent(new Event('change'));
            }
        }
    });
});
</script>

<style>
/* Bulk Actions Styles */
.table-active {
    background-color: rgba(0, 123, 255, 0.1) !important;
}

.bulk-checkbox {
    text-align: center;
}

.bulk-action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.bulk-actions-section {
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
    margin-bottom: 1rem;
}

.bulk-actions-section .form-group {
    margin-bottom: 0.5rem;
}

.bulk-actions-section .btn {
    margin-right: 0.5rem;
}

/* Checkbox styling */
#select-all-stories {
    transform: scale(1.2);
}

.bulk-checkbox input[type="checkbox"] {
    transform: scale(1.1);
}

/* Selected row highlighting */
tr.table-active td {
    background-color: rgba(0, 123, 255, 0.05);
    border-color: rgba(0, 123, 255, 0.2);
}

/* Bulk action buttons styling */
.bulk-action-btn {
    transition: all 0.3s ease;
}

.bulk-action-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Count badge in buttons */
.bulk-action-btn .badge {
    margin-left: 0.5rem;
}

/* Responsive bulk actions */
@media (max-width: 768px) {
    .bulk-actions-section {
        padding: 0.5rem;
    }
    
    .bulk-actions-section .btn {
        margin-bottom: 0.5rem;
        width: 100%;
    }
}
</style>