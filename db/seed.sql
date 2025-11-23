--
-- Database: `eems_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `approvals`
--

CREATE TABLE `approvals` (
  `id` int(11) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `approvals`
--

INSERT INTO `approvals` (`id`, `requester_id`, `type`, `status`, `created_at`, `processed_by`, `processed_at`) VALUES
(1, 3, 'Leave Request', 'pending', '2024-05-22 06:17:15', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `role` varchar(100) DEFAULT 'Invigilator'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `assignments`
--

INSERT INTO `assignments` (`id`, `faculty_id`, `exam_id`, `role`) VALUES
(1, 2, 1, 'Supervisor'),
(2, 3, 2, 'Examiner');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `exam_date` date NOT NULL,
  `department` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`id`, `title`, `exam_date`, `department`) VALUES
(1, 'Mid-Term Physics', '2024-06-01', 'Science'),
(2, 'Final Year History', '2024-06-16', 'Arts'),
(3, 'Calculus I', '2024-05-27', 'Mathematics');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `user_id` int(11) NOT NULL,
  `principal_access` tinyint(1) NOT NULL DEFAULT 0,
  `vice_access` tinyint(1) NOT NULL DEFAULT 0,
  `hod_access` tinyint(1) NOT NULL DEFAULT 0,
  `teacher_access` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`user_id`, `principal_access`, `vice_access`, `hod_access`, `teacher_access`) VALUES
(1, 1, 1, 1, 1),
(2, 0, 0, 0, 1),
(3, 0, 0, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `post` enum('teacher','hod','vice_principal','principal','admin') NOT NULL,
  `college_name` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
-- Password for Arjun admin: 1234
--

INSERT INTO `users` (`id`, `name`, `post`, `college_name`, `phone`, `email`, `password`, `status`, `created_at`) VALUES
(1, 'Arjun', 'admin', 'System Admin', '0000000000', 'arjun@admin.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oOoZ9Hfq6KVd0yM1qzGQ5J5BZfqWVK', 'verified', '2024-05-22 06:17:15'),
(2, 'Jane Doe', 'teacher', 'Science College', '1112223333', 'jane.doe@example.com', NULL, 'verified', '2024-05-22 06:17:15'),
(3, 'John Smith', 'hod', 'Arts College', '4445556666', 'john.smith@example.com', NULL, 'verified', '2024-05-22 06:17:15'),
(4, 'Pending User', 'teacher', 'Tech College', '9998887777', 'pending@example.com', NULL, 'pending', '2024-05-22 06:17:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `approvals`
--
ALTER TABLE `approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requester_id` (`requester_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `approvals`
--
ALTER TABLE `approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approvals`
--
ALTER TABLE `approvals`
  ADD CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;
