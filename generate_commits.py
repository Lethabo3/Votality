#!/usr/bin/env python3
import subprocess
import datetime
import random
import os

# Define holidays in 2024 (dates as "YYYY-MM-DD")
holidays = {
    "2024-01-01",  # New Year's Day
    "2024-01-15",  # Martin Luther King Jr. Day (observed)
    "2024-02-19",  # Presidents' Day
    "2024-05-27",  # Memorial Day
    "2024-07-04",  # Independence Day
    "2024-09-02",  # Labor Day
    "2024-10-14",  # Columbus Day
    "2024-11-11",  # Veterans Day
    "2024-11-28",  # Thanksgiving Day
    "2024-12-25",  # Christmas Day
}

# Set the date range for 2024
start_date = datetime.date(2024, 1, 1)
end_date = datetime.date(2024, 12, 31)

current_date = start_date

while current_date <= end_date:
    # Skip weekends (weekday() returns 0 for Monday ... 6 for Sunday)
    if current_date.weekday() < 5 and current_date.strftime("%Y-%m-%d") not in holidays:
        # Generate a random number of commits for the day between 10 and 30
        num_commits = random.randint(10, 30)
        for i in range(num_commits):
            # Generate a random time (here, within business hours 9 AM to 5 PM)
            hour = random.randint(9, 17)
            minute = random.randint(0, 59)
            second = random.randint(0, 59)
            commit_datetime = datetime.datetime.combine(current_date, datetime.time(hour, minute, second))
            commit_date_str = commit_datetime.strftime("%Y-%m-%dT%H:%M:%S")
            
            # Create a commit message that indicates the day and commit count
            commit_message = f"Commit on {current_date} (commit {i+1} of {num_commits})"
            
            # Set the commit date environment variables so Git uses our custom date
            env = os.environ.copy()
            env["GIT_AUTHOR_DATE"] = commit_date_str
            env["GIT_COMMITTER_DATE"] = commit_date_str
            
            # Create an empty commit with the custom date and message
            cmd = ["git", "commit", "--allow-empty", "-m", commit_message]
            print(f"Creating commit: {commit_date_str} -> {commit_message}")
            subprocess.run(cmd, env=env)
    current_date += datetime.timedelta(days=1)
