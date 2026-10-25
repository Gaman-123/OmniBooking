cmake_minimum_required(VERSION 3.15)
project(OmniBookingEngine CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

include_directories(include)

set(SOURCES
    src/dynamic_pricing.cpp
    src/inventory_allocator.cpp
    src/dag_scheduler.cpp
    src/rate_parity_auditor.cpp
    src/main.cpp
)

set(EXECUTABLE_OUTPUT_PATH ${CMAKE_CURRENT_SOURCE_DIR}/../bin)

add_executable(channel_engine ${SOURCES})
