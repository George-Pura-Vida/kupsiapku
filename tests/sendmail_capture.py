"""Disposable CI mail transport: capture the latest message without sending it."""
import pathlib, sys
pathlib.Path(sys.argv[1]).write_bytes(sys.stdin.buffer.read())
