# Log file change monitoring tool
This tool is used to track updates to the log files

## Usage

#### Regular

    $ bin/logmon /path/to/file

Command reads up to 100 lines of log file `/path/to/file`
and outputs them to stdout, remembering last position.

- The next run will start tracking from the place where the previous run has finished
- If content of the log file is overwritten then next run will start from the beginning
- Number of max lines read can be changed via `-m|--max` option
- State files directory can be changed using `-d|--dir` option

#### Regular, running external program

    $ bin/logmon /path/to/file /path/to/program

Instead of outputting lines to stdout Logmon will start `/path/to/program`
and will write lines to program stdin.
- Options and arguments for program can be passed after `--` argument:


    $ bin/logmon /path/to/file /path/to/program -- -a --opt

- If there are no new lines, program will not be started

#### Reset

    $ bin/logmon -r /path/to/file
    $ bin/logmon --reset /path/to/file

Resets remembered position for `/path/to/file`, next regular run will start from the beginning

#### Skip to the end

    $ bin/logmon -e /path/to/file
    $ bin/logmon --end /path/to/file

Sets position for `/path/to/file` file to the end

#### Help

    $ bin/logmon -h
    $ bin/logmon --help

Displays usage information

#### Version

    $ bin/logmon -v
    $ bin/logmon --version

Displays version information
