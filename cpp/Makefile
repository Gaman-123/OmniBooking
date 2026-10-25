CXX = g++
CXXFLAGS = -std=c++17 -O3 -Wall -Wextra -Iinclude
SRC = src/dynamic_pricing.cpp \
      src/inventory_allocator.cpp \
      src/dag_scheduler.cpp \
      src/rate_parity_auditor.cpp \
      src/main.cpp

OUT_DIR = ../bin
TARGET = $(OUT_DIR)/channel_engine.exe

all: $(TARGET)

$(TARGET): $(SRC)
	@mkdir $(subst /,\,$(OUT_DIR)) 2>nul || true
	$(CXX) $(CXXFLAGS) $(SRC) -o $(TARGET)

clean:
	del /Q $(subst /,\,$(TARGET)) 2>nul || true

.PHONY: all clean
