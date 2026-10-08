CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  value TEXT,
  note TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'viewer',
  department_code VARCHAR(20),
  sport_ids TEXT,
  password_hash VARCHAR(255),
  password_salt VARCHAR(255),
  password_updated_at VARCHAR(30),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at VARCHAR(30),
  updated_at VARCHAR(30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_invites (
  id INT PRIMARY KEY,
  token_hash VARCHAR(128) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL,
  name VARCHAR(190),
  role VARCHAR(50) NOT NULL DEFAULT 'viewer',
  department_code VARCHAR(20),
  sport_ids TEXT,
  invited_by VARCHAR(190),
  accepted_user_id INT,
  status VARCHAR(50) NOT NULL DEFAULT 'pending',
  expires_at VARCHAR(30) NOT NULL,
  accepted_at VARCHAR(30),
  revoked_at VARCHAR(30),
  created_at VARCHAR(30),
  CONSTRAINT fk_user_invites_user FOREIGN KEY (accepted_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS departments (
  id INT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  faculty_id INT,
  logo_url TEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
  id INT PRIMARY KEY,
  student_code VARCHAR(50) NOT NULL UNIQUE,
  prefix VARCHAR(50),
  first_name VARCHAR(190) NOT NULL,
  last_name VARCHAR(190) NOT NULL,
  department_code VARCHAR(20) NOT NULL,
  faculty_code VARCHAR(20) DEFAULT '102',
  entry_year VARCHAR(20),
  gender VARCHAR(20) DEFAULT 'open',
  status VARCHAR(50) DEFAULT 'active',
  is_eligible TINYINT(1) NOT NULL DEFAULT 1,
  card_raw_data TEXT,
  phone VARCHAR(50),
  photo_url TEXT,
  created_at VARCHAR(30),
  updated_at VARCHAR(30),
  CONSTRAINT fk_students_department FOREIGN KEY (department_code) REFERENCES departments(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sports (
  id INT PRIMARY KEY,
  name VARCHAR(190) NOT NULL UNIQUE,
  type VARCHAR(50) NOT NULL DEFAULT 'team',
  gender VARCHAR(20) NOT NULL DEFAULT 'open',
  min_players INT NOT NULL DEFAULT 1,
  max_players INT NOT NULL DEFAULT 1,
  reserve_players INT NOT NULL DEFAULT 0,
  rules TEXT,
  icon VARCHAR(100),
  logo_url TEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at VARCHAR(30),
  updated_at VARCHAR(30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tournaments (
  id INT PRIMARY KEY,
  academic_year VARCHAR(20),
  sport_id INT NOT NULL,
  name VARCHAR(190) NOT NULL,
  logo_url TEXT,
  format VARCHAR(50) NOT NULL DEFAULT 'knockout',
  starts_at VARCHAR(30),
  ends_at VARCHAR(30),
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  published_at VARCHAR(30),
  created_by VARCHAR(190),
  created_at VARCHAR(30),
  updated_at VARCHAR(30),
  CONSTRAINT fk_tournaments_sport FOREIGN KEY (sport_id) REFERENCES sports(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tournament_sports (
  tournament_id INT NOT NULL,
  sport_id INT NOT NULL,
  PRIMARY KEY (tournament_id, sport_id),
  CONSTRAINT fk_tournament_sports_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_tournament_sports_sport FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tournament_departments (
  id INT PRIMARY KEY,
  tournament_id INT NOT NULL,
  sport_id INT NOT NULL,
  department_code VARCHAR(20) NOT NULL,
  created_at VARCHAR(30),
  UNIQUE KEY uq_tournament_department (tournament_id, sport_id, department_code),
  CONSTRAINT fk_td_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_td_sport FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE,
  CONSTRAINT fk_td_department FOREIGN KEY (department_code) REFERENCES departments(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teams (
  id INT PRIMARY KEY,
  tournament_id INT NOT NULL,
  department_code VARCHAR(20) NOT NULL,
  name VARCHAR(190) NOT NULL,
  sport_id INT,
  logo_url TEXT,
  captain_student_id INT,
  status VARCHAR(50) NOT NULL DEFAULT 'submitted',
  rejection_reason TEXT,
  approved_by VARCHAR(190),
  approved_at VARCHAR(30),
  created_by VARCHAR(190),
  created_at VARCHAR(30),
  updated_at VARCHAR(30),
  CONSTRAINT fk_teams_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
  CONSTRAINT fk_teams_sport FOREIGN KEY (sport_id) REFERENCES sports(id),
  CONSTRAINT fk_teams_department FOREIGN KEY (department_code) REFERENCES departments(code),
  CONSTRAINT fk_teams_captain FOREIGN KEY (captain_student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS team_members (
  id INT PRIMARY KEY,
  team_id INT NOT NULL,
  student_id INT NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'player',
  shirt_number VARCHAR(20),
  field_position VARCHAR(50),
  created_at VARCHAR(30),
  UNIQUE (team_id, student_id),
  CONSTRAINT fk_team_members_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  CONSTRAINT fk_team_members_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS matches (
  id INT PRIMARY KEY,
  tournament_id INT NOT NULL,
  sport_id INT,
  rule_version_id INT,
  round_name VARCHAR(100),
  match_number VARCHAR(50),
  team1_id INT,
  team2_id INT,
  scheduled_at VARCHAR(30),
  venue VARCHAR(190),
  referee VARCHAR(190),
  status VARCHAR(50) NOT NULL DEFAULT 'scheduled',
  team1_score INT,
  team2_score INT,
  winner_team_id INT,
  current_period INT NOT NULL DEFAULT 1,
  clock_seconds INT NOT NULL DEFAULT 0,
  clock_status VARCHAR(30) NOT NULL DEFAULT 'stopped',
  overtime_count INT NOT NULL DEFAULT 0,
  confirmed_by VARCHAR(190),
  confirmed_at VARCHAR(30),
  created_at VARCHAR(30),
  updated_at VARCHAR(30),
  CONSTRAINT fk_matches_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
  CONSTRAINT fk_matches_sport FOREIGN KEY (sport_id) REFERENCES sports(id),
  CONSTRAINT fk_matches_team1 FOREIGN KEY (team1_id) REFERENCES teams(id),
  CONSTRAINT fk_matches_team2 FOREIGN KEY (team2_id) REFERENCES teams(id),
  CONSTRAINT fk_matches_winner FOREIGN KEY (winner_team_id) REFERENCES teams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS standings (
  id INT PRIMARY KEY,
  tournament_id INT NOT NULL,
  team_id INT NOT NULL,
  played INT NOT NULL DEFAULT 0,
  won INT NOT NULL DEFAULT 0,
  drawn INT NOT NULL DEFAULT 0,
  lost INT NOT NULL DEFAULT 0,
  points INT NOT NULL DEFAULT 0,
  scored INT NOT NULL DEFAULT 0,
  conceded INT NOT NULL DEFAULT 0,
  rank_number INT,
  updated_at VARCHAR(30),
  UNIQUE (tournament_id, team_id),
  CONSTRAINT fk_standings_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
  CONSTRAINT fk_standings_team FOREIGN KEY (team_id) REFERENCES teams(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news (
  id INT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  excerpt TEXT,
  body MEDIUMTEXT,
  cover_image_url TEXT,
  news_type VARCHAR(50) NOT NULL DEFAULT 'general',
  department_code VARCHAR(20),
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  is_pinned TINYINT(1) NOT NULL DEFAULT 0,
  published_at VARCHAR(30),
  author_email VARCHAR(190),
  created_at VARCHAR(30),
  updated_at VARCHAR(30),
  CONSTRAINT fk_news_department FOREIGN KEY (department_code) REFERENCES departments(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS universities (
  id INT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  logo_url TEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at VARCHAR(30),
  updated_at VARCHAR(30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faculties (
  id INT PRIMARY KEY,
  university_id INT NOT NULL,
  code VARCHAR(50) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  logo_url TEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at VARCHAR(30),
  updated_at VARCHAR(30),
  CONSTRAINT fk_faculties_university FOREIGN KEY (university_id) REFERENCES universities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sport_rule_versions (
  id INT PRIMARY KEY,
  sport_id INT NOT NULL,
  version_number INT NOT NULL DEFAULT 1,
  name VARCHAR(190) NOT NULL,
  category VARCHAR(100),
  player_type VARCHAR(50) NOT NULL DEFAULT 'team',
  team_size INT NOT NULL DEFAULT 1,
  scoring_system MEDIUMTEXT NOT NULL,
  match_format MEDIUMTEXT NOT NULL,
  period_format MEDIUMTEXT NOT NULL,
  overtime_rules MEDIUMTEXT NOT NULL,
  tie_break_rules MEDIUMTEXT NOT NULL,
  ranking_rules MEDIUMTEXT NOT NULL,
  event_types MEDIUMTEXT NOT NULL,
  custom_rules MEDIUMTEXT NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(190),
  created_at VARCHAR(30),
  updated_at VARCHAR(30),
  UNIQUE KEY uq_sport_rule_version (sport_id, version_number),
  CONSTRAINT fk_rule_versions_sport FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tournament_sport_settings (
  id INT PRIMARY KEY,
  tournament_id INT NOT NULL,
  sport_id INT NOT NULL,
  rule_version_id INT,
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  notes TEXT,
  created_at VARCHAR(30),
  updated_at VARCHAR(30),
  UNIQUE KEY uq_tournament_sport_settings (tournament_id, sport_id),
  CONSTRAINT fk_tss_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  CONSTRAINT fk_tss_sport FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE,
  CONSTRAINT fk_tss_rule FOREIGN KEY (rule_version_id) REFERENCES sport_rule_versions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_periods (
  id INT PRIMARY KEY,
  match_id INT NOT NULL,
  period_number INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  period_type VARCHAR(30) NOT NULL DEFAULT 'regular',
  duration_seconds INT NOT NULL DEFAULT 0,
  started_at VARCHAR(30),
  ended_at VARCHAR(30),
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  UNIQUE KEY uq_match_period (match_id, period_number),
  CONSTRAINT fk_match_periods_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS match_events (
  id INT PRIMARY KEY,
  match_id INT NOT NULL,
  event_type VARCHAR(80) NOT NULL,
  team_id INT,
  student_id INT,
  period_number INT NOT NULL DEFAULT 1,
  elapsed_seconds INT NOT NULL DEFAULT 0,
  added_time INT NOT NULL DEFAULT 0,
  points INT NOT NULL DEFAULT 0,
  card_type VARCHAR(30),
  note TEXT,
  metadata_json MEDIUMTEXT NOT NULL,
  is_voided TINYINT(1) NOT NULL DEFAULT 0,
  created_by VARCHAR(190),
  created_at VARCHAR(30),
  voided_by VARCHAR(190),
  voided_at VARCHAR(30),
  CONSTRAINT fk_match_events_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_events_team FOREIGN KEY (team_id) REFERENCES teams(id),
  CONSTRAINT fk_match_events_student FOREIGN KEY (student_id) REFERENCES students(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS news_links (
  news_id INT NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id INT NOT NULL,
  PRIMARY KEY (news_id, entity_type, entity_id),
  CONSTRAINT fk_news_links_news FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT PRIMARY KEY,
  user_email VARCHAR(190),
  action VARCHAR(100) NOT NULL,
  entity VARCHAR(100) NOT NULL,
  entity_id VARCHAR(100),
  detail_json MEDIUMTEXT,
  created_at VARCHAR(30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
  id INT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  description TEXT,
  category VARCHAR(50) NOT NULL DEFAULT 'general',
  priority VARCHAR(50) NOT NULL DEFAULT 'normal',
  status VARCHAR(50) NOT NULL DEFAULT 'todo',
  due_at VARCHAR(30),
  related_type VARCHAR(100),
  related_id VARCHAR(100),
  assignee_email VARCHAR(190),
  created_by VARCHAR(190),
  completed_at VARCHAR(30),
  created_at VARCHAR(30),
  updated_at VARCHAR(30)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_categories (
  id INT PRIMARY KEY,
  code VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  description TEXT,
  icon VARCHAR(80),
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_templates (
  id INT PRIMARY KEY,
  category_id INT NOT NULL,
  code VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  description TEXT,
  sections_json TEXT,
  display_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_report_templates_category FOREIGN KEY (category_id) REFERENCES report_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
  id INT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  category_id INT NOT NULL,
  template_id INT NOT NULL,
  tournament_id INT,
  academic_year VARCHAR(20),
  fiscal_year VARCHAR(20),
  semester VARCHAR(30),
  start_date VARCHAR(30),
  end_date VARCHAR(30),
  department VARCHAR(190),
  responsible_person VARCHAR(190),
  prepared_by VARCHAR(190),
  report_date VARCHAR(30),
  status VARCHAR(50) NOT NULL DEFAULT 'draft',
  general_json MEDIUMTEXT,
  performance_json MEDIUMTEXT,
  outcomes_json MEDIUMTEXT,
  problems_json MEDIUMTEXT,
  executive_summary MEDIUMTEXT,
  created_by VARCHAR(190),
  published_at VARCHAR(30),
  created_at VARCHAR(30),
  updated_at VARCHAR(30),
  CONSTRAINT fk_reports_category FOREIGN KEY (category_id) REFERENCES report_categories(id),
  CONSTRAINT fk_reports_template FOREIGN KEY (template_id) REFERENCES report_templates(id),
  CONSTRAINT fk_reports_tournament FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_kpis (
  id INT PRIMARY KEY,
  report_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  unit VARCHAR(50),
  target_value DECIMAL(14,2),
  actual_value DECIMAL(14,2),
  calculation_type VARCHAR(30) NOT NULL DEFAULT 'percentage',
  achievement DECIMAL(8,2),
  status VARCHAR(50),
  display_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_report_kpis_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_budgets (
  id INT PRIMARY KEY,
  report_id INT NOT NULL UNIQUE,
  allocated DECIMAL(14,2) NOT NULL DEFAULT 0,
  actual DECIMAL(14,2) NOT NULL DEFAULT 0,
  remaining DECIMAL(14,2) NOT NULL DEFAULT 0,
  usage_percentage DECIMAL(8,2) NOT NULL DEFAULT 0,
  note TEXT,
  CONSTRAINT fk_report_budgets_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS report_attachments (
  id INT PRIMARY KEY,
  report_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_url TEXT NOT NULL,
  file_type VARCHAR(100),
  file_size INT NOT NULL DEFAULT 0,
  note TEXT,
  created_at VARCHAR(30),
  CONSTRAINT fk_report_attachments_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
