<?php
// classes/faculty.php
require_once __DIR__ . '/../config/database.php';

class Faculty {
    public $panelist_id;
    public $first_name;
    public $last_name;
    public $email;
    public $department;
    public $expertise;
    // Note: The 'status' column is assumed to be in your 'faculty' table
    // to manage pending/active accounts.

    protected $db;

    public function __construct() {
        $this->db = new Database();
    }



    /**
    * Handles the public self-registration for a new panelist.
    * Inserts into faculty (status='pending') and panelist tables.
    * @param string $password The plain text password.
    * @return bool True on success, false on failure.
    */
    public function selfRegisterPanelist($password) {
        if ($this->emailExists($this->email)) {
            error_log("Registration Error: Email {$this->email} already exists.");
            return false;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        // Use first name + last name for initial username attempt, fallback to email part
        $usernameBase = strtolower(preg_replace('/[^a-z0-9]/', '', $this->first_name . $this->last_name));
        if(empty($usernameBase)) {
            $usernameBase = strtolower(explode('@', $this->email)[0]);
        }
        $username = $usernameBase;
        $designation = 'Panelist';
        $status = 'pending'; // New accounts must be approved

        $conn = $this->db->connect();

        // Ensure username is unique
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM panelist WHERE username = ?");
        $check_stmt->execute([$username]);
        $count = 1;
        while ($check_stmt->fetchColumn() > 0) {
            $username = $usernameBase . $count;
            $check_stmt->execute([$username]);
            $count++;
        }


        try {
            $conn->beginTransaction();

            // 1. Insert into the `faculty` table
            $sql = "INSERT INTO faculty (first_name, last_name, email, department, expertise, status, Date)
                    VALUES (:first_name, :last_name, :email, :department, :expertise, :status, NOW())"; // Add Date
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':first_name', $this->first_name);
            $stmt->bindParam(':last_name', $this->last_name);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':department', $this->department);
            $stmt->bindParam(':expertise', $this->expertise);
            $stmt->bindParam(':status', $status);
            $stmt->execute();

            $faculty_id = $conn->lastInsertId();
            if(!$faculty_id) { // Check if faculty insert succeeded
                throw new Exception("Failed to insert into faculty table.");
            }

            // 2. Insert into the `panelist` table
            // Make sure all required columns exist in your 'panelist' table schema
            $sql2 = "INSERT INTO panelist (
                        panelist_id, username, password, first_name, last_name,
                        email, designation, role, status, created_at
                    )
                    VALUES (
                        :panelist_id, :username, :password, :first_name, :last_name,
                        :email, :designation, 'Panel', :status, NOW()
                    )";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bindParam(':panelist_id', $faculty_id, PDO::PARAM_INT);
            $stmt2->bindParam(':username', $username); // Use generated unique username
            $stmt2->bindParam(':password', $hashedPassword);
            $stmt2->bindParam(':first_name', $this->first_name);
            $stmt2->bindParam(':last_name', $this->last_name);
            $stmt2->bindParam(':email', $this->email);
            $stmt2->bindParam(':designation', $designation);
            $stmt2->bindParam(':status', $status); // Use pending status here too
            $stmt2->execute();

            $conn->commit();
            return true;

        } catch (Exception $e) { // Catch generic Exception too
            $conn->rollBack();
            error_log("Self Register Error: " . $e->getMessage());
            return false;
        }
    }



    /**
    * Retrieves all faculty members with 'pending' status (awaiting approval).
    * Fetches necessary details for display.
    * @return array
    */
    public function getPendingPanelists() {
        $conn = $this->db->connect();
        // Fetch from panelist table where status is pending, join faculty for details
        $sql = "SELECT p.*, f.department, f.expertise, f.Date as faculty_date_added
                FROM panelist p
                LEFT JOIN faculty f ON p.panelist_id = f.panelist_id
                WHERE p.status = 'pending'
                ORDER BY p.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
    * Approves a pending panelist by setting status to 'active' in both tables.
    * Generates a unique username based on name if not already set or unique.
    * @param int $panelistId
    * @return string|bool Generated/Confirmed username on success, false on failure.
    */
    public function approvePanelist($panelistId) {
        $conn = $this->db->connect();
        try {
            // Get panelist details to generate/confirm username
            $stmt_get = $conn->prepare("SELECT email, first_name, last_name, username FROM panelist WHERE panelist_id = ? AND status = 'pending'");
            $stmt_get->execute([$panelistId]);
            $panelist = $stmt_get->fetch(PDO::FETCH_ASSOC);

            if (!$panelist) return false; // Panelist not found or already active/rejected

            $username = $panelist['username']; // Get potentially existing username

            // Generate username if empty or needs to be unique
            if (empty($username)) {
                // Generate username from first name + last name (lowercase, no spaces)
                $usernameBase = strtolower(preg_replace('/[^a-z0-9]/', '', $panelist['first_name'] . $panelist['last_name']));
                if(empty($usernameBase)) { // Fallback if name is empty/invalid
                    $usernameBase = strtolower(explode('@', $panelist['email'])[0]);
                }
                $username = $usernameBase;

                // Ensure username is unique
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM panelist WHERE username = ? AND panelist_id != ?");
                $check_stmt->execute([$username, $panelistId]);
                $count = 1;
                while ($check_stmt->fetchColumn() > 0) {
                    $username = $usernameBase . $count;
                    $check_stmt->execute([$username, $panelistId]);
                    $count++;
                }
            } else {
                // Check if existing username conflicts with another user
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM panelist WHERE username = ? AND panelist_id != ?");
                $check_stmt->execute([$username, $panelistId]);
                if($check_stmt->fetchColumn() > 0) {
                    // Username conflict, generate a new one
                    $usernameBase = $username; // Use existing as base
                    $count = 1;
                    $check_stmt->execute([$username, $panelistId]); // Re-execute check for the while loop
                    while ($check_stmt->fetchColumn() > 0) {
                        $username = $usernameBase . $count;
                        $check_stmt->execute([$username, $panelistId]);
                        $count++;
                    }
                }
            }


            $conn->beginTransaction();
            // Update status in panelist table AND set username
            $sql_p = "UPDATE panelist SET status = 'active', username = ? WHERE panelist_id = ?";
            $stmt_p = $conn->prepare($sql_p);
            $stmt_p->execute([$username, $panelistId]);

            // Update status in faculty table
            $sql_f = "UPDATE faculty SET status = 'active' WHERE panelist_id = ?";
            $stmt_f = $conn->prepare($sql_f);
            $stmt_f->execute([$panelistId]);

            $conn->commit();
            return $username; // Return the username used

        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Approve Panelist Error: " . $e->getMessage());
            return false;
        }
    }

    /**
    * Rejects and deletes a pending panelist from both tables.
    * @param int $panelistId
    * @return bool
    */
    public function rejectPanelist($panelistId) {
        $conn = $this->db->connect();
        try {
            // Ensure the panelist is actually pending before deleting
            $stmt_check = $conn->prepare("SELECT status FROM panelist WHERE panelist_id = ?");
            $stmt_check->execute([$panelistId]);
            $status = $stmt_check->fetchColumn();

            if ($status !== 'pending') {
                error_log("Attempted to reject non-pending panelist: ID " . $panelistId);
                return false; // Or throw an exception
            }

            $conn->beginTransaction();

            // 1. Delete from panelist table (associated with login)
            $sql2 = "DELETE FROM panelist WHERE panelist_id = :id";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bindParam(':id', $panelistId, PDO::PARAM_INT);
            $stmt2->execute();

            // 2. Delete from faculty table (associated with profile/details)
            $sql = "DELETE FROM faculty WHERE panelist_id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $panelistId, PDO::PARAM_INT);
            $stmt->execute();
            
            // Check if rows were actually deleted
            if ($stmt->rowCount() > 0 || $stmt2->rowCount() > 0) {
                 $conn->commit();
                 return true;
            } else {
                 // Panelist ID might not exist in one or both tables if something went wrong during registration
                 $conn->rollBack();
                 error_log("Reject Panelist Warning: No rows deleted for panelist ID " . $panelistId);
                 return false; // Indicate potential issue
            }

        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Reject Panelist Error: " . $e->getMessage());
            return false;
        }
    }


    // =================================================================
    // MODIFIED: Original Admin Add Faculty (Status: active)
    // Now adds Date and ensures consistency between tables
    // =================================================================

    /**
    * Admin method to directly add a faculty member (active status).
    * Ensures data exists in both faculty and panelist tables.
    * Generates unique username.
    */
    public function addFaculty($password = null) {
        $conn = $this->db->connect();
        $status = 'active'; // Admin adds directly as active
        $designation = 'Panelist';
        $role = 'Panel'; // Default role

        // Generate username from first name + last name (lowercase, no spaces)
        $usernameBase = strtolower(preg_replace('/[^a-z0-9]/', '', $this->first_name . $this->last_name));
        if(empty($usernameBase)) { // Fallback if name is empty/invalid
            $usernameBase = strtolower(explode('@', $this->email)[0]);
        }
        $username = $usernameBase;

        // Ensure username is unique
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM panelist WHERE username = ?");
        $check_stmt->execute([$username]);
        $count = 1;
        while ($check_stmt->fetchColumn() > 0) {
            $username = $usernameBase . $count;
            $check_stmt->execute([$username]);
            $count++;
        }

        // Determine password: Use provided, generate from last name, or fallback
        if ($password) {
             $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        } elseif (!empty($this->last_name)) {
             $hashed_password = password_hash(strtolower($this->last_name), PASSWORD_DEFAULT); // Hash the default too
        } else {
             // Fallback if last name is also empty - consider requiring password input
             $hashed_password = password_hash('defaultpass', PASSWORD_DEFAULT); // Or handle error
        }


        try {
            $conn->beginTransaction();

            // Insert into faculty table
            $sql = "INSERT INTO faculty (first_name, last_name, email, department, expertise, status, Date)
                    VALUES (:first_name, :last_name, :email, :department, :expertise, :status, NOW())"; // Add Date
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':first_name', $this->first_name);
            $stmt->bindParam(':last_name', $this->last_name);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':department', $this->department);
            $stmt->bindParam(':expertise', $this->expertise);
            $stmt->bindParam(':status', $status);
            $stmt->execute();

            $faculty_id = $conn->lastInsertId();
             if(!$faculty_id) {
                throw new Exception("Failed to insert into faculty table.");
            }

            // Insert into panelist table
             $sql2 = "INSERT INTO panelist (
                        panelist_id, username, password, first_name, last_name,
                        email, designation, role, status, created_at
                     )
                    VALUES (
                        :panelist_id, :username, :password, :first_name, :last_name,
                        :email, :designation, :role, :status, NOW()
                    )";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bindParam(':panelist_id', $faculty_id, PDO::PARAM_INT);
            $stmt2->bindParam(':username', $username); // Use unique username
            $stmt2->bindParam(':password', $hashed_password);
            $stmt2->bindParam(':first_name', $this->first_name);
            $stmt2->bindParam(':last_name', $this->last_name);
            $stmt2->bindParam(':email', $this->email);
            $stmt2->bindParam(':designation', $designation);
            $stmt2->bindParam(':role', $role);
            $stmt2->bindParam(':status', $status);
            $stmt2->execute();

            $conn->commit();
            return true;

        } catch (Exception $e) { // Catch generic Exception
            $conn->rollBack();
            error_log("Add Faculty Error: " . $e->getMessage());
            return false;
        }
    }

    // =================================================================
    // View Faculty Method - REVISED to base on PANELISTS and show counts
    // =================================================================

    /**
     * Fetches ACTIVE faculty/panelist list with assignment counts.
     * Queries primarily from 'panelist' table.
     * Removed date filter to show all active panelists.
     * @return array List of active panelists with details and counts.
     */
    public function viewFaculty() { // Removed $filterDate parameter
        $conn = $this->db->connect();

        // SQL to fetch active panelists and join faculty details + counts
        $sql = "SELECT
                    p.panelist_id,
                    p.first_name,
                    p.last_name,
                    p.email,
                    p.username,
                    p.designation,
                    p.status, -- Get status from panelist table
                    p.created_at as panelist_created_at, -- Alias panelist creation date
                    f.department,
                    f.expertise,
                    f.Date as faculty_date_added, -- Alias faculty record date
                    (SELECT COUNT(DISTINCT a_sched.schedule_id)
                     FROM assignment a_sched
                     WHERE a_sched.panelist_id = p.panelist_id) as assigned_panels_count,
                    (SELECT COUNT(DISTINCT a_group.group_id)
                     FROM assignment a_group
                     WHERE a_group.panelist_id = p.panelist_id) as assigned_groups_count
                FROM panelist p -- Start from panelist table
                LEFT JOIN faculty f ON p.panelist_id = f.panelist_id -- Join faculty details
                WHERE p.status = 'active' -- Filter for active panelists
                ORDER BY p.last_name ASC, p.first_name ASC"; // Order by name

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute(); // No parameters needed now
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("View Faculty Error: " . $e->getMessage());
            return []; // Return empty array on error
        }
    }


    // =================================================================
    // Other Original Methods (Ensure consistency)
    // =================================================================


    public function getFacultyById($id) {
        $conn = $this->db->connect();
        // Fetch from faculty joined with panelist
        $sql = "SELECT f.*, p.username, p.designation, p.role, p.contact_number, p.created_at as panelist_created_at, p.status as panelist_status
                FROM faculty f
                LEFT JOIN panelist p ON f.panelist_id = p.panelist_id
                WHERE f.panelist_id = :id LIMIT 1";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
             error_log("Get Faculty By ID Error: " . $e->getMessage());
             return false;
        }
    }

    /**
    * Updates faculty details in BOTH faculty and panelist tables.
    * NOTE: Does not update username or password here. Assumes status is handled elsewhere.
    */
    public function updateFaculty($id, $first_name, $last_name, $email, $department, $expertise, $contact_number = null, $designation = 'Panelist', $role = 'Panel') {
        $conn = $this->db->connect();

        try {
            $conn->beginTransaction();

            // Update faculty table
            $sql = "UPDATE faculty
                    SET first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        department = :department,
                        expertise = :expertise
                        -- status = :status -- Decide if status should be updated here
                    WHERE panelist_id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':first_name', $first_name);
            $stmt->bindParam(':last_name', $last_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':department', $department);
            $stmt->bindParam(':expertise', $expertise);
            // $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // Update panelist table (sync relevant fields)
            $sql2 = "UPDATE panelist
                    SET first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        contact_number = :contact_number,
                        designation = :designation,
                        role = :role
                        -- status = :status -- Sync status if needed
                    WHERE panelist_id = :id";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bindParam(':first_name', $first_name);
            $stmt2->bindParam(':last_name', $last_name);
            $stmt2->bindParam(':email', $email);
            $stmt2->bindParam(':contact_number', $contact_number);
            $stmt2->bindParam(':designation', $designation);
            $stmt2->bindParam(':role', $role);
             // $stmt2->bindParam(':status', $status);
            $stmt2->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt2->execute();

            $conn->commit();
            return true;

        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Update Faculty Error: " . $e->getMessage());
            return false;
        }
    }


    /**
    * Deletes faculty details from BOTH faculty and panelist tables.
    * Also removes related availability and assignments.
    */
    public function deleteFaculty($id) {
        $conn = $this->db->connect();

        try {
            // Use transaction for safer deletion across tables
            $conn->beginTransaction();

             // 0. Delete assignments first (FK constraint likely)
            $sql_assign = "DELETE FROM assignment WHERE panelist_id = :id";
            $stmt_assign = $conn->prepare($sql_assign);
            $stmt_assign->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_assign->execute();

            // 1. Delete availability (FK constraint likely)
            $sql_avail = "DELETE FROM availability WHERE panelist_id = :id";
            $stmt_avail = $conn->prepare($sql_avail);
            $stmt_avail->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_avail->execute();

            // 2. Delete from panelist (login details)
            $sql_panelist = "DELETE FROM panelist WHERE panelist_id = :id";
            $stmt_panelist = $conn->prepare($sql_panelist);
            $stmt_panelist->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_panelist->execute();

            // 3. Delete from faculty (profile details)
            $sql_faculty = "DELETE FROM faculty WHERE panelist_id = :id";
            $stmt_faculty = $conn->prepare($sql_faculty);
            $stmt_faculty->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt_faculty->execute();

            // Check if at least one row was affected (faculty or panelist)
            if ($stmt_panelist->rowCount() > 0 || $stmt_faculty->rowCount() > 0) {
                 $conn->commit();
                return true;
            } else {
                // ID might not have existed
                $conn->rollBack();
                 error_log("Delete Faculty Warning: No rows deleted for panelist ID " . $id);
                return false;
            }

        } catch (PDOException $e) {
            $conn->rollBack();
            error_log("Delete Faculty Error: " . $e->getMessage());
            return false;
        }
    }


    public function getFacultyCount() { // Removed filterDate
        $conn = $this->db->connect();

        // Count active panelists from the panelist table
        $sql = "SELECT COUNT(*) AS total FROM panelist WHERE status = 'active'";
        $params = [];

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['total'] : 0;
        } catch (PDOException $e) {
             error_log("Get Faculty Count Error: " . $e->getMessage());
             return 0;
        }
    }


    public function getFacultyByDepartment($department) {
        $conn = $this->db->connect();
        // Select from panelist joined with faculty, filtered by department and status
        $sql = "SELECT p.*, f.department, f.expertise, f.Date as faculty_date_added
                FROM panelist p
                JOIN faculty f ON p.panelist_id = f.panelist_id
                WHERE f.department = :department AND p.status = 'active'
                ORDER BY p.last_name ASC";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':department', $department);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
             error_log("Get Faculty By Department Error: " . $e->getMessage());
             return [];
        }
    }


    public function getFacultyByExpertise($expertise) {
        $conn = $this->db->connect();
         // Select from panelist joined with faculty, filtered by expertise and status
        $sql = "SELECT p.*, f.department, f.expertise, f.Date as faculty_date_added
                FROM panelist p
                JOIN faculty f ON p.panelist_id = f.panelist_id
                WHERE f.expertise LIKE :expertise AND p.status = 'active'
                ORDER BY p.last_name ASC";
        try {
            $stmt = $conn->prepare($sql);
            $expertiseParam = "%{$expertise}%";
            $stmt->bindParam(':expertise', $expertiseParam);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
             error_log("Get Faculty By Expertise Error: " . $e->getMessage());
             return [];
        }
    }

    // =================================================================
    // Utility Functions
    // =================================================================

    /**
    * Check if an email exists in the panelist table (as it's used for login).
    * Can exclude a specific ID during updates.
    */
    public function emailExists($email, $excludeId = null) {
        $conn = $this->db->connect();
        $sql = "SELECT COUNT(*) as count FROM panelist WHERE email = :email"; // Check panelist table
        $params = [':email' => $email];

        if ($excludeId && is_numeric($excludeId)) { // Ensure excludeId is valid
            $sql .= " AND panelist_id != :id";
            $params[':id'] = $excludeId;
        }

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
             error_log("Email Exists Check Error: " . $e->getMessage());
             return false; // Safer to assume error means potential existence
        }
    }


    public function getAllDepartments() {
        $conn = $this->db->connect();
        // Select distinct departments from faculty table
        $sql = "SELECT DISTINCT department FROM faculty WHERE department IS NOT NULL AND department != '' ORDER BY department ASC";
        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
             error_log("Get All Departments Error: " . $e->getMessage());
             return [];
        }
    }


    public function resetPanelistPassword($panelist_id, $newPassword) {
        $conn = $this->db->connect();
        if (empty($newPassword) || strlen($newPassword) < 6) { // Add basic password length check
             error_log("Reset Password Error: Password too short for panelist ID " . $panelist_id);
             return false;
         }

        $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
        $sql = "UPDATE panelist SET password = :password WHERE panelist_id = :id";
        try {
             $stmt = $conn->prepare($sql);
             $stmt->bindParam(':password', $hashed_password);
             $stmt->bindParam(':id', $panelist_id, PDO::PARAM_INT);
             return $stmt->execute();
        } catch (PDOException $e) {
             error_log("Reset Password Error: " . $e->getMessage());
             return false;
        }
    }
    
    /**
     * Get all panelist emails for a specific schedule
     * @param int $schedule_id
     * @return array
     */
    public function getPanelistEmailsForSchedule($schedule_id) {
        $conn = $this->db->connect();
        // Join assignment with panelist table for email
        $sql = "SELECT p.email
                FROM assignment a
                JOIN panelist p ON a.panelist_id = p.panelist_id 
                WHERE a.schedule_id = :schedule_id"; 
        try {
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':schedule_id', $schedule_id, PDO::PARAM_INT);
            $stmt->execute();
            // Return an array of email strings
            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        } catch (PDOException $e) {
            error_log("Get Panelist Emails Error: " . $e->getMessage());
            return [];
        }
    }
}



