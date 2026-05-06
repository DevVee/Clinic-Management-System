<?php
// Ensure variables are defined
if (!isset($clinic_contact_1)) $clinic_contact_1 = '0917 123 4567';
if (!isset($clinic_contact_2)) $clinic_contact_2 = '0928 765 4321';
?>

<div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">
    <div class="card fade-in">
        <div class="card-header">
            <i class="fas fa-phone"></i> Manage Clinic Contact Numbers
        </div>
        <div class="card-body">
            <form id="contactsForm">
                <input type="hidden" name="action" value="update_contacts">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label for="clinic_contact_1" class="form-label">Clinic Contact Number 1</label>
                        <input type="text" class="form-control" id="clinic_contact_1" name="clinic_contact_1" value="<?= htmlspecialchars($clinic_contact_1) ?>" pattern="(\+63|0)9\d{9}" required>
                        <div class="form-text">Enter a valid Philippine mobile number (e.g., 09171234567 or +639171234567)</div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label for="clinic_contact_2" class="form-label">Clinic Contact Number 2</label>
                        <input type="text" class="form-control" id="clinic_contact_2" name="clinic_contact_2" value="<?= htmlspecialchars($clinic_contact_2) ?>" pattern="(\+63|0)9\d{9}" required>
                        <div class="form-text">Enter a valid Philippine mobile number (e.g., 09287654321 or +639287654321)</div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </form>
        </div>
    </div>
</div>