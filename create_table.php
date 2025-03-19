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
    membership_reg_date DATE NOT NULL,
    membership_reg_time TIME NOT NULL,
    bill VARCHAR(255) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table membership_reg created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

// SQL to add membership_id column to membership_reg table
$sql = "ALTER TABLE membership_reg
ADD COLUMN membership_id INT NOT NULL AFTER user_id,
ADD FOREIGN KEY (membership_id) REFERENCES memberships(membership_id)";

if ($conn->query($sql) === TRUE) {
    echo "Column membership_id added to membership_reg table successfully";
} else {
    echo "Error adding column: " . $conn->error;
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
    feedback_content TEXT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    feedback_date DATE NOT NULL,
    feedback_time TIME NOT NULL,
    status ENUM('Pending', 'Reviewed') DEFAULT 'Pending',
    FOREIGN KEY (user_id) REFERENCES users(user_id)
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



// Close connection
$conn->close();
?> 