<?php
$servername = "localhost";
$username = "root";     // your MySQL username
$password = "";         // your MySQL password
$dbname = "arenax";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// SQL to create memberships table
$sql = "CREATE TABLE memberships (
    membership_id INT AUTO_INCREMENT PRIMARY KEY,
    membership_type VARCHAR(50) NOT NULL,
    membership_price DECIMAL(10,2) NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Table memberships created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// SQL to create users table
$sql = "CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    dob DATE NOT NULL,
    mobile VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    membership_id INT DEFAULT 1,
    role INT DEFAULT 1,
    reset_token VARCHAR(100) DEFAULT NULL,
    token_expiry DATETIME DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (membership_id) REFERENCES memberships(membership_id)
)";


if ($conn->query($sql) === TRUE) {
    echo "Table users created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create activity table
$sql = "CREATE TABLE activity (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    activity_type VARCHAR(50) UNIQUE NOT NULL
)";

if ($conn->query($sql) === TRUE) {
    echo "Table activity created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create sub_activity_name table
$sql = "CREATE TABLE sub_activity_name (
    sub_act_id INT AUTO_INCREMENT PRIMARY KEY,
    sub_act_name VARCHAR(100) NOT NULL,
    activity_id INT NOT NULL,
    membership_type ENUM('normal', 'standard', 'premium') NOT NULL,
    FOREIGN KEY (activity_id) REFERENCES activity(activity_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table sub_activity_name created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create sub_activity table
$sql = "CREATE TABLE sub_activity (
    sub_activity_id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    sub_activity_price DECIMAL(10,2) NOT NULL,
    sub_activity_image VARCHAR(255) NOT NULL,
    sub_act_id INT NOT NULL,
    FOREIGN KEY (activity_id) REFERENCES activity(activity_id),
    FOREIGN KEY (sub_act_id) REFERENCES sub_activity_name(sub_act_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table sub_activity created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create timeslots table
$sql = "CREATE TABLE timeslots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    sub_activity_id INT NOT NULL,
    slot_date DATE NOT NULL,
    slot_start_time TIME NOT NULL,
    slot_end_time TIME NOT NULL,
    max_participants INT NOT NULL,
    current_participants INT DEFAULT 0,
    slot_full BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sub_activity_id) REFERENCES sub_activity(sub_activity_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table timeslots created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create booking table
$sql = "CREATE TABLE booking (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sub_activity_id INT NOT NULL,
    slot_id INT NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    bill VARCHAR(255) NOT NULL,
    reminder BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (sub_activity_id) REFERENCES sub_activity(sub_activity_id),
    FOREIGN KEY (slot_id) REFERENCES timeslots(slot_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table booking created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$sql = "CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    activity_id INT NOT NULL,
    event_title VARCHAR(100) NOT NULL,
    event_description TEXT NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    event_location VARCHAR(255) NOT NULL,
    max_participants INT NOT NULL,
    event_age_limit INT NOT NULL,
    event_price DECIMAL(10,2) NOT NULL,
    event_image VARCHAR(255) DEFAULT NULL,
    created_at_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at_time TIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (activity_id) REFERENCES activity(activity_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table events created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create event_registration table
$sql = "CREATE TABLE event_registration (
    event_reg_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    user_id INT NOT NULL,
    bill VARCHAR(255) NOT NULL,
    FOREIGN KEY (event_id) REFERENCES events(event_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table event_registration created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create membership_reg table
$sql = "CREATE TABLE membership_reg (
    membership_reg_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    membership_id INT NOT NULL,
    membership_reg_date DATE NOT NULL,
    membership_reg_time TIME NOT NULL,
    expiration_date DATE NULL,
    bill VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (membership_id) REFERENCES memberships(membership_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table membership_reg created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create payment table
$sql = "CREATE TABLE payment (
    payment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_time TIME NOT NULL,
    booking_id INT,
    event_reg_id INT,
    membership_reg_id INT,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (booking_id) REFERENCES booking(booking_id),
    FOREIGN KEY (event_reg_id) REFERENCES event_registration(event_reg_id),
    FOREIGN KEY (membership_reg_id) REFERENCES membership_reg(membership_reg_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table payment created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create feedback table
$sql = "CREATE TABLE feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    sub_act_id INT NOT NULL,
    activity_name VARCHAR(100) NOT NULL,
    feedback_content TEXT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    feedback_date DATE NOT NULL,
    feedback_time TIME NOT NULL,
    status ENUM('Pending', 'Reviewed') DEFAULT 'Pending',
    event_id INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (activity_id) REFERENCES activity(activity_id),
    FOREIGN KEY (sub_act_id) REFERENCES sub_activity_name(sub_act_id),
    CONSTRAINT fk_feedback_event FOREIGN KEY (event_id) REFERENCES events(event_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table feedback created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create notification table
$sql = "CREATE TABLE notification (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_viewed BOOLEAN DEFAULT FALSE,
    created_at_date DATE NOT NULL,
    created_at_time TIME NOT NULL,
    viewed_at_date DATE,
    viewed_at_time TIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table notification created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to create recurring_bookings table
$sql = "CREATE TABLE recurring_bookings (
    recurring_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sub_activity_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    selected_days VARCHAR(50) NOT NULL, -- Store days as comma-separated string (e.g., '1,3,5' for Mon,Wed,Fri)
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    bill VARCHAR(255), -- Added column for storing bill path
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (sub_activity_id) REFERENCES sub_activity(sub_activity_id),
    CHECK (DATEDIFF(end_date, start_date) <= 56) -- Maximum 8 weeks
)";

if ($conn->query($sql) === TRUE) {
    echo "Table recurring_bookings created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// SQL to create recurring_slots table
$sql = "CREATE TABLE recurring_slots (
    slot_id INT AUTO_INCREMENT PRIMARY KEY,
    recurring_id INT NOT NULL,
    booking_date DATE NOT NULL,
    timeslot_id INT NOT NULL,
    status ENUM('pending', 'booked', 'cancelled') DEFAULT 'pending',
    booking_id INT NULL,
    FOREIGN KEY (recurring_id) REFERENCES recurring_bookings(recurring_id),
    FOREIGN KEY (timeslot_id) REFERENCES timeslots(slot_id),
    FOREIGN KEY (booking_id) REFERENCES booking(booking_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table recurring_slots created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Add events to the activities array
$eventsQuery = "SELECT a.activity_type, e.event_title 
               FROM events e
               JOIN activity a ON e.activity_id = a.activity_id
               ORDER BY e.event_title";
$eventsResult = $conn->query($eventsQuery);

while ($row = $eventsResult->fetch_assoc()) {
    $activities[$row['activity_type']][] = $row['event_title'];
}

// Close connection
$conn->close();

require_once 'db_connect.php';

try {
    // Modify the expiration_date column to allow NULL values
    $sql = "ALTER TABLE membership_reg MODIFY COLUMN expiration_date DATE NULL";
    $conn->exec($sql);
    echo "Modified membership_reg table to allow NULL expiration_date values.";
} catch (PDOException $e) {
    echo "Error modifying table: " . $e->getMessage();
}
?> 