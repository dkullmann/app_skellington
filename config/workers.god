require 'rubygems'
require 'god'

ROOT = "/apps/production/example.com/default"
require File.expand_path(File.dirname(__FILE__) + '/cakephp_god.rb')
God.pid_file_directory = "/apps/pids"
God.log_level = :error

%w(default quick).each do |queue|
  CakePHPGod.queue_workers(queue, 1)
end

# run as root
%w(root).each do |queue|
  CakePHPGod.queue_workers(queue, 1, 'root', 'root')
end
