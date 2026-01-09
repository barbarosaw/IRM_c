#!/usr/bin/env python3
"""
AbroadWorks Management System - TimeWorks JSON Importer
Imports TimeWorks data from JSON files into MySQL database

@author ikinciadam@gmail.com
"""

import json
import mysql.connector
from mysql.connector import Error
import os
from datetime import datetime
import sys

# Color codes for terminal output
class Colors:
    SUCCESS = '\033[0;32m'
    ERROR = '\033[0;31m'
    WARNING = '\033[0;33m'
    INFO = '\033[0;36m'
    RESET = '\033[0m'

def color_log(message, msg_type='info'):
    colors = {
        'success': Colors.SUCCESS,
        'error': Colors.ERROR,
        'warning': Colors.WARNING,
        'info': Colors.INFO
    }
    color = colors.get(msg_type, Colors.INFO)
    print(f"{color}{message}{Colors.RESET}")

# Database connection
def get_db_connection():
    try:
        connection = mysql.connector.connect(
            host='localhost',
            database='irm_sys',
            user='irm_sys_sr',
            password='JEegMl1pf!@5l3ev',
            charset='utf8mb4',
            collation='utf8mb4_unicode_ci'
        )
        return connection
    except Error as e:
        color_log(f"Database connection error: {e}", 'error')
        sys.exit(1)

def main():
    color_log("=" * 50, 'info')
    color_log("  TimeWorks JSON Import Script", 'info')
    color_log("=" * 50, 'info')
    print()

    start_time = datetime.now()
    total_records = 0
    errors = []

    # Connect to database
    db = get_db_connection()
    cursor = db.cursor()

    # Base path for JSON files
    json_path = os.path.join(os.path.dirname(__file__), '../../mds/')

    # =====================================================
    # 1. Import Users
    # =====================================================
    color_log("1. Importing Users...", 'info')

    users_file = os.path.join(json_path, 'users.json')
    if not os.path.exists(users_file):
        color_log(f"Error: users.json not found at {users_file}", 'error')
        sys.exit(1)

    with open(users_file, 'r', encoding='utf-8') as f:
        users_data = json.load(f)

    users_count = 0
    users_inserted = 0
    users_updated = 0

    for user in users_data:
        try:
            # Check if exists
            cursor.execute("SELECT id FROM twr_users WHERE user_id = %s", (user['user_id'],))
            exists = cursor.fetchone()

            cursor.execute("""
                INSERT INTO twr_users
                (user_id, full_name, email, timezone, status, user_status, last_login_local, roles, created_at, synced_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
                ON DUPLICATE KEY UPDATE
                    full_name = VALUES(full_name),
                    email = VALUES(email),
                    timezone = VALUES(timezone),
                    status = VALUES(status),
                    user_status = VALUES(user_status),
                    last_login_local = VALUES(last_login_local),
                    roles = VALUES(roles),
                    synced_at = VALUES(synced_at),
                    updated_at = NOW()
            """, (
                user['user_id'],
                user['full_name'],
                user['email'],
                user.get('timezone', 'UTC'),
                user.get('status', 'active'),
                user.get('user_status', 'normal'),
                user.get('last_login_local'),
                user.get('roles', 'User'),
                user.get('created_at', datetime.now().strftime('%Y-%m-%d %H:%M:%S')),
                user.get('synced_at', datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
            ))

            if exists:
                users_updated += 1
            else:
                users_inserted += 1

            users_count += 1

            if users_count % 50 == 0:
                print(".", end="", flush=True)

        except Error as e:
            errors.append(f"User Import Error ({user.get('email', 'unknown')}): {e}")

    db.commit()
    print()
    color_log(f"✓ Users: {users_count} processed ({users_inserted} new, {users_updated} updated)", 'success')
    total_records += users_count

    # =====================================================
    # 2. Import Projects
    # =====================================================
    color_log("2. Importing Projects...", 'info')

    projects_file = os.path.join(json_path, 'projects.json')
    with open(projects_file, 'r', encoding='utf-8') as f:
        projects_data = json.load(f)

    projects_count = 0
    projects_inserted = 0
    projects_updated = 0

    for project in projects_data:
        try:
            # Check if exists
            cursor.execute("SELECT id FROM twr_projects WHERE project_id = %s", (project['project_id'],))
            exists = cursor.fetchone()

            cursor.execute("""
                INSERT INTO twr_projects
                (project_id, name, description, status, progress, is_billable, task_count, member_count, created_at, updated_at, synced_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, NOW())
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    status = VALUES(status),
                    progress = VALUES(progress),
                    is_billable = VALUES(is_billable),
                    task_count = VALUES(task_count),
                    member_count = VALUES(member_count),
                    updated_at = VALUES(updated_at),
                    synced_at = VALUES(synced_at)
            """, (
                project['project_id'],
                project['name'],
                project.get('description'),
                project.get('status', 'active'),
                project.get('progress', 'Pending'),
                project.get('is_billable', 0),
                project.get('task_count', 0),
                project.get('member_count', 0),
                project.get('created_at', datetime.now().strftime('%Y-%m-%d %H:%M:%S')),
                project.get('updated_at', datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
            ))

            if exists:
                projects_updated += 1
            else:
                projects_inserted += 1

            projects_count += 1

            if projects_count % 50 == 0:
                print(".", end="", flush=True)

        except Error as e:
            errors.append(f"Project Import Error ({project.get('name', 'unknown')}): {e}")

    db.commit()
    print()
    color_log(f"✓ Projects: {projects_count} processed ({projects_inserted} new, {projects_updated} updated)", 'success')
    total_records += projects_count

    # =====================================================
    # 3. Import User-Project Relationships
    # =====================================================
    color_log("3. Importing User-Project Relationships...", 'info')

    user_projects_file = os.path.join(json_path, 'user_projects.json')
    with open(user_projects_file, 'r', encoding='utf-8') as f:
        user_projects_data = json.load(f)

    # Clear existing relationships
    cursor.execute("TRUNCATE TABLE twr_user_projects")

    relationships_count = 0

    for user_project in user_projects_data:
        try:
            if 'projects' not in user_project or not isinstance(user_project['projects'], list):
                continue

            for project in user_project['projects']:
                cursor.execute("""
                    INSERT IGNORE INTO twr_user_projects
                    (user_id, project_id, assigned_at, synced_at)
                    VALUES (%s, %s, NOW(), NOW())
                """, (
                    user_project['user_id'],
                    project['project_id']
                ))

                relationships_count += 1

                if relationships_count % 100 == 0:
                    print(".", end="", flush=True)

        except Error as e:
            errors.append(f"User-Project Relationship Error ({user_project.get('user_id', 'unknown')}): {e}")

    db.commit()
    print()
    color_log(f"✓ User-Project Relationships: {relationships_count} processed", 'success')
    total_records += relationships_count

    # =====================================================
    # 4. Import User Shifts
    # =====================================================
    color_log("4. Importing User Shifts...", 'info')

    shifts_file = os.path.join(json_path, 'user_shifts.json')
    with open(shifts_file, 'r', encoding='utf-8') as f:
        shifts_data = json.load(f)

    # Clear existing shifts
    cursor.execute("TRUNCATE TABLE twr_user_shifts")

    shifts_count = 0

    for user_shift in shifts_data:
        try:
            # Get user_id from full_name
            cursor.execute("SELECT user_id FROM twr_users WHERE full_name = %s LIMIT 1", (user_shift['full_name'],))
            user_result = cursor.fetchone()

            if not user_result:
                color_log(f"Warning: User not found: {user_shift['full_name']}", 'warning')
                continue

            user_id = user_result[0]

            if 'schedule' not in user_shift or not isinstance(user_shift['schedule'], dict):
                continue

            for day, schedule in user_shift['schedule'].items():
                cursor.execute("""
                    INSERT INTO twr_user_shifts
                    (user_id, full_name, day_of_week, start_time, end_time, is_off, synced_at)
                    VALUES (%s, %s, %s, %s, %s, %s, NOW())
                    ON DUPLICATE KEY UPDATE
                        start_time = VALUES(start_time),
                        end_time = VALUES(end_time),
                        is_off = VALUES(is_off),
                        synced_at = VALUES(synced_at)
                """, (
                    user_id,
                    user_shift['full_name'],
                    day,
                    schedule.get('start', '09:00'),
                    schedule.get('end', '17:00'),
                    1 if schedule.get('is_off', False) else 0
                ))

                shifts_count += 1

            if shifts_count % 100 == 0:
                print(".", end="", flush=True)

        except Error as e:
            errors.append(f"Shift Import Error ({user_shift.get('full_name', 'unknown')}): {e}")

    db.commit()
    print()
    color_log(f"✓ User Shifts: {shifts_count} processed", 'success')
    total_records += shifts_count

    # =====================================================
    # 5. Log to sync_log
    # =====================================================
    duration = (datetime.now() - start_time).total_seconds()

    cursor.execute("""
        INSERT INTO twr_sync_log
        (sync_type, status, records_processed, records_added, records_updated, records_failed, started_at, completed_at, duration_seconds)
        VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), %s)
    """, (
        'full',
        'success' if not errors else 'partial',
        total_records,
        users_inserted + projects_inserted,
        users_updated + projects_updated,
        len(errors),
        start_time.strftime('%Y-%m-%d %H:%M:%S'),
        int(duration)
    ))

    db.commit()

    # =====================================================
    # Summary
    # =====================================================
    print()
    color_log("=" * 50, 'info')
    color_log("  Import Summary", 'info')
    color_log("=" * 50, 'info')
    color_log(f"Total Records: {total_records}", 'success')
    color_log(f"Duration: {duration:.2f} seconds", 'info')
    print()

    if errors:
        color_log(f"Errors encountered: {len(errors)}", 'error')
        for error in errors[:10]:
            color_log(f"  - {error}", 'error')
        if len(errors) > 10:
            color_log(f"  ... and {len(errors) - 10} more errors", 'error')
    else:
        color_log("✓ Import completed successfully with no errors!", 'success')

    print()
    color_log("=" * 50, 'info')

    # Close connection
    cursor.close()
    db.close()

if __name__ == '__main__':
    main()
