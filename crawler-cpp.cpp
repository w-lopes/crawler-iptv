#include <iostream>
#include <vector>
using namespace std;

int main() {
	vector<string> lists;

	lists.push_back("https://bit.ly/faustinotv");

	for (auto i = lists.begin(); i != lists.end(); i++) {
		cout << *i << endl;
	}

	return 0;
}
