CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT,
  note TEXT
);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY,
  email TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'viewer',
  department_code TEXT,
  sport_ids TEXT,
  password_hash TEXT,
  password_salt TEXT,
  password_updated_at TEXT,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS user_invites (
  id INTEGER PRIMARY KEY,
  token_hash TEXT NOT NULL UNIQUE,
  email TEXT NOT NULL,
  name TEXT,
  role TEXT NOT NULL DEFAULT 'viewer',
  department_code TEXT,
  sport_ids TEXT,
  invited_by TEXT,
  accepted_user_id INTEGER,
  status TEXT NOT NULL DEFAULT 'pending',
  expires_at TEXT NOT NULL,
  accepted_at TEXT,
  revoked_at TEXT,
  created_at TEXT,
  FOREIGN KEY (accepted_user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS departments (
  id INTEGER PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  faculty_id INTEGER,
  logo_url TEXT,
  is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS students (
  id INTEGER PRIMARY KEY,
  student_code TEXT NOT NULL UNIQUE,
  prefix TEXT,
  first_name TEXT NOT NULL,
  last_name TEXT NOT NULL,
  department_code TEXT NOT NULL,
  faculty_code TEXT DEFAULT '102',
  entry_year TEXT,
  gender TEXT DEFAULT 'open',
  status TEXT DEFAULT 'active',
  is_eligible INTEGER NOT NULL DEFAULT 1,
  card_raw_data TEXT,
  phone TEXT,
  created_at TEXT,
  updated_at TEXT,
  FOREIGN KEY (department_code) REFERENCES departments(code)
);

CREATE TABLE IF NOT EXISTS sports (
  id INTEGER PRIMARY KEY,
  name TEXT NOT NULL UNIQUE,
  type TEXT NOT NULL DEFAULT 'team',
  gender TEXT NOT NULL DEFAULT 'open',
  min_players INTEGER NOT NULL DEFAULT 1,
  max_players INTEGER NOT NULL DEFAULT 1,
  reserve_players INTEGER NOT NULL DEFAULT 0,
  rules TEXT,
  icon TEXT,
  logo_url TEXT,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS tournaments (
  id INTEGER PRIMARY KEY,
  academic_year TEXT,
  sport_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  logo_url TEXT,
  format TEXT NOT NULL DEFAULT 'knockout',
  starts_at TEXT,
  ends_at TEXT,
  status TEXT NOT NULL DEFAULT 'draft',
  published_at TEXT,
  created_by TEXT,
  created_at TEXT,
  updated_at TEXT,
  FOREIGN KEY (sport_id) REFERENCES sports(id)
);

CREATE TABLE IF NOT EXISTS tournament_sports (
  tournament_id INTEGER NOT NULL,
  sport_id INTEGER NOT NULL,
  PRIMARY KEY (tournament_id, sport_id),
  FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
  FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS teams (
  id INTEGER PRIMARY KEY,
  tournament_id INTEGER NOT NULL,
  department_code TEXT NOT NULL,
  name TEXT NOT NULL,
  sport_id INTEGER,
  logo_url TEXT,
  captain_student_id INTEGER,
  status TEXT NOT NULL DEFAULT 'submitted',
  rejection_reason TEXT,
  approved_by TEXT,
  approved_at TEXT,
  created_by TEXT,
  created_at TEXT,
  updated_at TEXT,
  FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
  FOREIGN KEY (sport_id) REFERENCES sports(id),
  FOREIGN KEY (department_code) REFERENCES departments(code),
  FOREIGN KEY (captain_student_id) REFERENCES students(id)
);

CREATE TABLE IF NOT EXISTS team_members (
  id INTEGER PRIMARY KEY,
  team_id INTEGER NOT NULL,
  student_id INTEGER NOT NULL,
  role TEXT NOT NULL DEFAULT 'player',
  shirt_number TEXT,
  field_position TEXT,
  created_at TEXT,
  UNIQUE (team_id, student_id),
  FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE IF NOT EXISTS matches (
  id INTEGER PRIMARY KEY,
  tournament_id INTEGER NOT NULL,
  sport_id INTEGER,
  rule_version_id INTEGER,
  round_name TEXT,
  match_number TEXT,
  team1_id INTEGER,
  team2_id INTEGER,
  scheduled_at TEXT,
  venue TEXT,
  referee TEXT,
  status TEXT NOT NULL DEFAULT 'scheduled',
  team1_score INTEGER,
  team2_score INTEGER,
  winner_team_id INTEGER,
  current_period INTEGER NOT NULL DEFAULT 1,
  clock_seconds INTEGER NOT NULL DEFAULT 0,
  clock_status TEXT NOT NULL DEFAULT 'stopped',
  overtime_count INTEGER NOT NULL DEFAULT 0,
  confirmed_by TEXT,
  confirmed_at TEXT,
  created_at TEXT,
  updated_at TEXT,
  FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
  FOREIGN KEY (sport_id) REFERENCES sports(id),
  FOREIGN KEY (team1_id) REFERENCES teams(id),
  FOREIGN KEY (team2_id) REFERENCES teams(id),
  FOREIGN KEY (winner_team_id) REFERENCES teams(id)
);

CREATE TABLE IF NOT EXISTS standings (
  id INTEGER PRIMARY KEY,
  tournament_id INTEGER NOT NULL,
  team_id INTEGER NOT NULL,
  played INTEGER NOT NULL DEFAULT 0,
  won INTEGER NOT NULL DEFAULT 0,
  drawn INTEGER NOT NULL DEFAULT 0,
  lost INTEGER NOT NULL DEFAULT 0,
  points INTEGER NOT NULL DEFAULT 0,
  scored INTEGER NOT NULL DEFAULT 0,
  conceded INTEGER NOT NULL DEFAULT 0,
  rank_number INTEGER,
  updated_at TEXT,
  UNIQUE (tournament_id, team_id),
  FOREIGN KEY (tournament_id) REFERENCES tournaments(id),
  FOREIGN KEY (team_id) REFERENCES teams(id)
);

CREATE TABLE IF NOT EXISTS news (
  id INTEGER PRIMARY KEY,
  title TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  excerpt TEXT,
  body TEXT,
  cover_image_url TEXT,
  news_type TEXT NOT NULL DEFAULT 'general',
  department_code TEXT,
  status TEXT NOT NULL DEFAULT 'draft',
  is_pinned INTEGER NOT NULL DEFAULT 0,
  published_at TEXT,
  author_email TEXT,
  created_at TEXT,
  updated_at TEXT,
  FOREIGN KEY (department_code) REFERENCES departments(code)
);

CREATE TABLE IF NOT EXISTS universities (
  id INTEGER PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  logo_url TEXT,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS faculties (
  id INTEGER PRIMARY KEY,
  university_id INTEGER NOT NULL,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  logo_url TEXT,
  is_active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT,
  updated_at TEXT,
  FOREIGN KEY (university_id) REFERENCES universities(id)
);

CREATE TABLE IF NOT EXISTS sport_rule_versions (
  id INTEGER PRIMARY KEY,
  sport_id INTEGER NOT NULL,
  version_number INTEGER NOT NULL DEFAULT 1,
  name TEXT NOT NULL,
  category TEXT,
  player_type TEXT NOT NULL DEFAULT 'team',
  team_size INTEGER NOT NULL DEFAULT 1,
  scoring_system TEXT NOT NULL DEFAULT '{}',
  match_format TEXT NOT NULL DEFAULT '{}',
  period_format TEXT NOT NULL DEFAULT '{}',
  overtime_rules TEXT NOT NULL DEFAULT '{}',
  tie_break_rules TEXT NOT NULL DEFAULT '{}',
  ranking_rules TEXT NOT NULL DEFAULT '{}',
  event_types TEXT NOT NULL DEFAULT '[]',
  custom_rules TEXT NOT NULL DEFAULT '{}',
  is_active INTEGER NOT NULL DEFAULT 1,
  created_by TEXT,
  created_at TEXT,
  updated_at TEXT,
  UNIQUE (sport_id, version_number),
  FOREIGN KEY (sport_id) REFERENCES sports(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS match_periods (
  id INTEGER PRIMARY KEY,
  match_id INTEGER NOT NULL,
  period_number INTEGER NOT NULL,
  name TEXT NOT NULL,
  period_type TEXT NOT NULL DEFAULT 'regular',
  duration_seconds INTEGER NOT NULL DEFAULT 0,
  started_at TEXT,
  ended_at TEXT,
  status TEXT NOT NULL DEFAULT 'pending',
  UNIQUE (match_id, period_number),
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS match_events (
  id INTEGER PRIMARY KEY,
  match_id INTEGER NOT NULL,
  event_type TEXT NOT NULL,
  team_id INTEGER,
  student_id INTEGER,
  period_number INTEGER NOT NULL DEFAULT 1,
  elapsed_seconds INTEGER NOT NULL DEFAULT 0,
  added_time INTEGER NOT NULL DEFAULT 0,
  points INTEGER NOT NULL DEFAULT 0,
  card_type TEXT,
  note TEXT,
  metadata_json TEXT NOT NULL DEFAULT '{}',
  is_voided INTEGER NOT NULL DEFAULT 0,
  created_by TEXT,
  created_at TEXT,
  voided_by TEXT,
  voided_at TEXT,
  FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  FOREIGN KEY (team_id) REFERENCES teams(id),
  FOREIGN KEY (student_id) REFERENCES students(id)
);

CREATE TABLE IF NOT EXISTS news_links (
  news_id INTEGER NOT NULL,
  entity_type TEXT NOT NULL,
  entity_id INTEGER NOT NULL,
  PRIMARY KEY (news_id, entity_type, entity_id),
  FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id INTEGER PRIMARY KEY,
  user_email TEXT,
  action TEXT NOT NULL,
  entity TEXT NOT NULL,
  entity_id TEXT,
  detail_json TEXT,
  created_at TEXT
);

CREATE TABLE IF NOT EXISTS tasks (
  id INTEGER PRIMARY KEY,
  title TEXT NOT NULL,
  description TEXT,
  category TEXT NOT NULL DEFAULT 'general',
  priority TEXT NOT NULL DEFAULT 'normal',
  status TEXT NOT NULL DEFAULT 'todo',
  due_at TEXT,
  related_type TEXT,
  related_id TEXT,
  assignee_email TEXT,
  created_by TEXT,
  completed_at TEXT,
  created_at TEXT,
  updated_at TEXT
);

CREATE TABLE IF NOT EXISTS report_categories (
  id INTEGER PRIMARY KEY,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  description TEXT,
  icon TEXT,
  display_order INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS report_templates (
  id INTEGER PRIMARY KEY,
  category_id INTEGER NOT NULL,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  description TEXT,
  sections_json TEXT,
  display_order INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES report_categories(id)
);

CREATE TABLE IF NOT EXISTS reports (
  id INTEGER PRIMARY KEY,
  title TEXT NOT NULL,
  category_id INTEGER NOT NULL,
  template_id INTEGER NOT NULL,
  tournament_id INTEGER,
  academic_year TEXT,
  fiscal_year TEXT,
  semester TEXT,
  start_date TEXT,
  end_date TEXT,
  department TEXT,
  responsible_person TEXT,
  prepared_by TEXT,
  report_date TEXT,
  status TEXT NOT NULL DEFAULT 'draft',
  general_json TEXT,
  performance_json TEXT,
  outcomes_json TEXT,
  problems_json TEXT,
  executive_summary TEXT,
  created_by TEXT,
  published_at TEXT,
  created_at TEXT,
  updated_at TEXT,
  FOREIGN KEY (category_id) REFERENCES report_categories(id),
  FOREIGN KEY (template_id) REFERENCES report_templates(id),
  FOREIGN KEY (tournament_id) REFERENCES tournaments(id)
);

CREATE TABLE IF NOT EXISTS report_kpis (
  id INTEGER PRIMARY KEY,
  report_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  unit TEXT,
  target_value REAL,
  actual_value REAL,
  calculation_type TEXT NOT NULL DEFAULT 'percentage',
  achievement REAL,
  status TEXT,
  display_order INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS report_budgets (
  id INTEGER PRIMARY KEY,
  report_id INTEGER NOT NULL UNIQUE,
  allocated REAL NOT NULL DEFAULT 0,
  actual REAL NOT NULL DEFAULT 0,
  remaining REAL NOT NULL DEFAULT 0,
  usage_percentage REAL NOT NULL DEFAULT 0,
  note TEXT,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS report_attachments (
  id INTEGER PRIMARY KEY,
  report_id INTEGER NOT NULL,
  file_name TEXT NOT NULL,
  file_url TEXT NOT NULL,
  file_type TEXT,
  file_size INTEGER NOT NULL DEFAULT 0,
  note TEXT,
  created_at TEXT,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_students_department ON students(department_code);
CREATE INDEX IF NOT EXISTS idx_tournaments_sport ON tournaments(sport_id);
CREATE INDEX IF NOT EXISTS idx_tournament_sports_sport ON tournament_sports(sport_id);
CREATE INDEX IF NOT EXISTS idx_teams_tournament ON teams(tournament_id);
CREATE INDEX IF NOT EXISTS idx_team_members_team ON team_members(team_id);
CREATE INDEX IF NOT EXISTS idx_team_members_student ON team_members(student_id);
CREATE INDEX IF NOT EXISTS idx_matches_tournament ON matches(tournament_id);
CREATE INDEX IF NOT EXISTS idx_match_events_match ON match_events(match_id, elapsed_seconds);
CREATE INDEX IF NOT EXISTS idx_rule_versions_sport ON sport_rule_versions(sport_id, is_active);
CREATE INDEX IF NOT EXISTS idx_teams_sport ON teams(sport_id);
CREATE INDEX IF NOT EXISTS idx_news_status_type ON news(status, news_type);
CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_tasks_status_due ON tasks(status, due_at);
CREATE INDEX IF NOT EXISTS idx_tasks_assignee ON tasks(assignee_email);
CREATE INDEX IF NOT EXISTS idx_user_invites_status_expires ON user_invites(status, expires_at);
CREATE INDEX IF NOT EXISTS idx_report_templates_category ON report_templates(category_id);
CREATE INDEX IF NOT EXISTS idx_reports_status_year ON reports(status, academic_year);
CREATE INDEX IF NOT EXISTS idx_reports_category_template ON reports(category_id, template_id);
CREATE INDEX IF NOT EXISTS idx_reports_tournament ON reports(tournament_id);
CREATE INDEX IF NOT EXISTS idx_report_kpis_report ON report_kpis(report_id);
CREATE INDEX IF NOT EXISTS idx_report_attachments_report ON report_attachments(report_id);
