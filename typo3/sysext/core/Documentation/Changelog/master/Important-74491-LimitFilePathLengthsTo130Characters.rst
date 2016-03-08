=============================================================
Important: #74491 - Limit file path lengths to 130 characters
=============================================================

Description
===========

The maximum length of a file path in TYPO3 has been limited to 130 characters to reduce the amount of issues on Windows hosts.

A test has been added to Travis builds which enforces this limit.